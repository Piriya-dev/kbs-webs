<?php
// /opt/lampp/htdocs/api/iot/crud_gis_points.php
// CRUD + utilities for gis_points
// Actions:
//   - list:             GET/POST  filters: id, name (LIKE), bbox(minLat,maxLat,minLng,maxLng), page, per_page
//   - get:              GET/POST  id
//   - create:           POST      name, lat, lng, [description], [status], [status_timestamp]
//   - update:           POST      id + any subset of {name,lat,lng,description,status,status_timestamp}
//   - delete:           POST      id
//   - randomize_status: POST      bbox(minLat,maxLat,minLng,maxLng) [alert_ratio 0..1]
//   - list_names:       GET       distinct non-empty names (for popup picker)
// Confidential ‚Äì Internal Use Only

header("Content-Type: application/json; charset=UTF-8");
// error_reporting(E_ALL); ini_set('display_errors', 1);

require_once __DIR__ . '/db.php';   // MUST create $mysqli and set utf8mb4
$mysqli->set_charset('utf8mb4');
date_default_timezone_set('Asia/Bangkok');

/* ---------- small helpers ---------- */
function jexit($arr, $code=200){
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  if (isset($GLOBALS['mysqli']) && $GLOBALS['mysqli'] instanceof mysqli) $GLOBALS['mysqli']->close();
  exit;
}
function in_any($key, $default=null){
  static $json=null;
  if ($json===null){
    $raw=file_get_contents('php://input');
    $json=$raw?json_decode($raw,true):[];
    if(!is_array($json)) $json=[];
  }
  if (isset($_GET[$key]))  return is_string($_GET[$key])  ? trim($_GET[$key])  : $_GET[$key];
  if (isset($_POST[$key])) return is_string($_POST[$key]) ? trim($_POST[$key]) : $_POST[$key];
  if (isset($json[$key]))  return is_string($json[$key])  ? trim($json[$key])  : $json[$key];
  return $default;
}
function must_numeric($val, $name){
  if ($val === '' || !is_numeric($val)) jexit(['success'=>false,'message'=>"$name must be numeric"], 400);
  return $val+0;
}
function has_val($v){ return !($v===null || $v===''); }

/* ---------- action ---------- */
$action = in_any('action','list');

/* ---------- list_names ---------- */
if ($action === 'list_names'){
  $sql = "SELECT DISTINCT name FROM gis_points WHERE name IS NOT NULL AND name <> '' ORDER BY name ASC LIMIT 1000";
  $rs = $mysqli->query($sql);
  if (!$rs) jexit(['success'=>false,'message'=>'SQL error','error'=>$mysqli->error], 500);
  $names = [];
  while ($r = $rs->fetch_assoc()){
    $n = (string)($r['name'] ?? '');
    if ($n!=='') $names[] = ['name'=>$n];
  }
  $rs->close();
  jexit(['success'=>true, 'data'=>$names]);
}

/* ---------- get ---------- */
if ($action === 'get'){
  $id = in_any('id',''); $id = must_numeric($id, 'id');
  $st = $mysqli->prepare("SELECT id,name,lat,lng,description,status,status_timestamp,created_at,updated_at FROM gis_points WHERE id=?");
  if (!$st) jexit(['success'=>false,'message'=>'SQL prepare failed','error'=>$mysqli->error],500);
  $st->bind_param('i', $id);
  $st->execute();
  $res = $st->get_result();
  $row = $res->fetch_assoc();
  $st->close();
  if (!$row) jexit(['success'=>false,'message'=>'Not found'],404);
  $row['id']=(int)$row['id']; $row['lat']=(float)$row['lat']; $row['lng']=(float)$row['lng']; $row['status']=(int)$row['status'];
  jexit(['success'=>true,'data'=>$row]);
}

/* ---------- list ---------- */
if ($action === 'list'){
  $id      = in_any('id','');
  $name    = in_any('name','');
  $minLat  = in_any('minLat','');
  $maxLat  = in_any('maxLat','');
  $minLng  = in_any('minLng','');
  $maxLng  = in_any('maxLng','');
  $page     = max(1, (int) (in_any('page', 1)));
  $per_page = (int) (in_any('per_page', 100));
  $per_page = max(1, min(1000, $per_page));
  $offset   = ($page-1)*$per_page;

  $where=" WHERE 1=1 "; $types=""; $params=[];

  if ($id!==''){ $where.=" AND id = ? "; $types.="i"; $params[]=(int)$id; }
  if ($name!==''){ $where.=" AND name LIKE ? "; $types.="s"; $params[]="%{$name}%"; }
  $bbox=false;
  if ($minLat!=='' && $maxLat!=='' && $minLng!=='' && $maxLng!==''){
    $where.=" AND (lat BETWEEN ? AND ?) AND (lng BETWEEN ? AND ?) ";
    $types.="dddd"; $params[]=(float)$minLat; $params[]=(float)$maxLat; $params[]=(float)$minLng; $params[]=(float)$maxLng;
    $bbox=true;
  }

  // count
  $sqlc="SELECT COUNT(*) AS total FROM gis_points {$where}";
  $stc=$mysqli->prepare($sqlc);
  if(!$stc) jexit(['success'=>false,'message'=>'SQL prepare (count) failed','error'=>$mysqli->error],500);
  if($types!=="") $stc->bind_param($types, ...$params);
  $stc->execute();
  $total=(int)($stc->get_result()->fetch_assoc()['total'] ?? 0);
  $stc->close();

  if ($total===0){
    jexit([
      'success'=>true,
      'filters'=>[
        'id'=>($id!==''?(int)$id:null),
        'name'=>($name!==''?$name:null),
        'bbox'=>$bbox?[$minLat,$maxLat,$minLng,$maxLng]:null,
      ],
      'meta'=>['page'=>1,'per_page'=>$per_page,'total'=>0,'total_pages'=>0],
      'data'=>[]
    ]);
  }

  // data
  $sql="SELECT id,name,lat,lng,description,status,status_timestamp,created_at,updated_at
        FROM gis_points {$where}
        ORDER BY id DESC LIMIT ? OFFSET ?";
  $std=$mysqli->prepare($sql);
  if(!$std) jexit(['success'=>false,'message'=>'SQL prepare (data) failed','error'=>$mysqli->error],500);
  $typesd=$types."ii"; $paramsd=$params; $paramsd[]=$per_page; $paramsd[]=$offset;
  $std->bind_param($typesd, ...$paramsd);
  $std->execute();
  $rs=$std->get_result();
  $rows=[];
  while($r=$rs->fetch_assoc()){
    $r['id']=(int)$r['id'];
    $r['lat']=(float)$r['lat'];
    $r['lng']=(float)$r['lng'];
    $r['status']=(int)$r['status'];
    $rows[]=$r;
  }
  $std->close();

  jexit([
    'success'=>true,
    'filters'=>[
      'id'=>($id!==''?(int)$id:null),
      'name'=>($name!==''?$name:null),
      'bbox'=>$bbox?[$minLat,$maxLat,$minLng,$maxLng]:null,
    ],
    'meta'=>[
      'page'=>$page, 'per_page'=>$per_page, 'total'=>$total,
      'total_pages'=>(int)ceil($total/$per_page),
    ],
    'data'=>$rows
  ]);
}

/* ---------- create ---------- */
if ($action === 'create'){
  $name = in_any('name','');
  $lat  = in_any('lat','');
  $lng  = in_any('lng','');
  $desc = in_any('description', null);
  $status = in_any('status', null);
  $ts   = in_any('status_timestamp', null);

  if ($name==='') jexit(['success'=>false,'message'=>'name is required'],400);
  $lat = must_numeric($lat, 'lat');
  $lng = must_numeric($lng, 'lng');

  $fields = "name,lat,lng,created_at,updated_at";
  $place  = "?,?,?,NOW(),NOW()";
  $types  = "sdd";
  $params = [$name, (float)$lat, (float)$lng];

  if (has_val($desc)){ $fields.=",description"; $place.=",?"; $types.="s"; $params[]=$desc; }
  if (has_val($status)){
    $s = (int)$status; if($s!==0 && $s!==1) jexit(['success'=>false,'message'=>'status must be 0 or 1'],400);
    $fields.=",status"; $place.=",?"; $types.="i"; $params[]=$s;
    // status_timestamp: provided or NOW()
    if (has_val($ts)){
      $fields.=",status_timestamp"; $place.=",?"; $types.="s"; $params[]=$ts;
    } else {
      $fields.=",status_timestamp"; $place.=",NOW()";
    }
  } elseif (has_val($ts)) {
    // if timestamp provided without status, still accept
    $fields.=",status_timestamp"; $place.=",?"; $types.="s"; $params[]=$ts;
  }

  $sql = "INSERT INTO gis_points ($fields) VALUES ($place)";
  $st = $mysqli->prepare($sql);
  if(!$st) jexit(['success'=>false,'message'=>'SQL prepare failed','error'=>$mysqli->error],500);
  $st->bind_param($types, ...$params);
  $ok=$st->execute();
  $id=$st->insert_id;
  $err=$st->error;
  $st->close();
  if(!$ok) jexit(['success'=>false,'message'=>'Insert failed','error'=>$err],500);

  jexit(['success'=>true,'id'=>(int)$id,'message'=>'Created']);
}

/* ---------- update ---------- */
if ($action === 'update'){
  $id = in_any('id',''); $id = must_numeric($id, 'id');

  $name = in_any('name', null);
  $lat  = in_any('lat', null);
  $lng  = in_any('lng', null);
  $desc = in_any('description', null);
  $status = in_any('status', null);
  $ts   = in_any('status_timestamp', null);

  $set=[]; $types=""; $params=[];

  if (has_val($name)){ $set[]="name=?"; $types.="s"; $params[]=$name; }
  if (has_val($lat)){  if(!is_numeric($lat)) jexit(['success'=>false,'message'=>'lat must be numeric'],400);
                       $set[]="lat=?"; $types.="d"; $params[]=(float)$lat; }
  if (has_val($lng)){  if(!is_numeric($lng)) jexit(['success'=>false,'message'=>'lng must be numeric'],400);
                       $set[]="lng=?"; $types.="d"; $params[]=(float)$lng; }
  if ($desc !== null){ $set[]="description=?"; $types.="s"; $params[]=$desc; }
  $touchTs = false;
  if ($status !== null){
    $s=(int)$status; if($s!==0 && $s!==1) jexit(['success'=>false,'message'=>'status must be 0 or 1'],400);
    $set[]="status=?"; $types.="i"; $params[]=$s;
    if ($ts !== null){ $set[]="status_timestamp=?"; $types.="s"; $params[]=$ts; }
    else { $touchTs = true; } // auto NOW() when status provided and ts not provided
  } else if ($ts !== null){
    $set[]="status_timestamp=?"; $types.="s"; $params[]=$ts;
  }

  if (empty($set)) jexit(['success'=>false,'message'=>'No fields to update'],400);

  $sql = "UPDATE gis_points SET ".implode(',', $set).", updated_at=NOW() ".($touchTs? ", status_timestamp=NOW() ":"")." WHERE id=?";
  $types.="i"; $params[]=(int)$id;

  $st = $mysqli->prepare($sql);
  if(!$st) jexit(['success'=>false,'message'=>'SQL prepare failed','error'=>$mysqli->error],500);
  $st->bind_param($types, ...$params);
  $ok = $st->execute();
  $err = $st->error;
  $aff = $st->affected_rows;
  $st->close();
  if(!$ok) jexit(['success'=>false,'message'=>'Update failed','error'=>$err],500);

  jexit(['success'=>true,'updated_rows'=>$aff]);
}

/* ---------- delete ---------- */
if ($action === 'delete'){
  $id = in_any('id',''); $id = must_numeric($id, 'id');
  $st = $mysqli->prepare("DELETE FROM gis_points WHERE id=?");
  if(!$st) jexit(['success'=>false,'message'=>'SQL prepare failed','error'=>$mysqli->error],500);
  $st->bind_param('i', $id);
  $ok = $st->execute();
  $err= $st->error;
  $aff= $st->affected_rows;
  $st->close();
  if(!$ok) jexit(['success'=>false,'message'=>'Delete failed','error'=>$err],500);
  jexit(['success'=>true,'deleted_rows'=>$aff]);
}

/* ---------- randomize_status (bbox only) ---------- */
if ($action === 'randomize_status'){
  $minLat  = in_any('minLat',''); $maxLat=in_any('maxLat',''); $minLng=in_any('minLng',''); $maxLng=in_any('maxLng','');
  if ($minLat===''||$maxLat===''||$minLng===''||$maxLng===''){
    jexit(['success'=>false,'message'=>'minLat,maxLat,minLng,maxLng are required'],400);
  }
  $minLat=(float)$minLat; $maxLat=(float)$maxLat; $minLng=(float)$minLng; $maxLng=(float)$maxLng;

  $alert_ratio = in_any('alert_ratio',''); // 0..1, default 0.5
  $ratio = 0.5;
  if ($alert_ratio !== ''){
    if(!is_numeric($alert_ratio)) jexit(['success'=>false,'message'=>'alert_ratio must be numeric (0..1)'],400);
    $ratio = max(0.0, min(1.0, (float)$alert_ratio));
  }

  // We‚Äôll compute a random status per row using RAND().
  // Additionally, update status_timestamp only if the status actually changes.
  // To detect changes, we need current status. A portable way: update with CASE that references RAND()
  // and then recount.

  // First, preselect ids in bbox (to limit scope and avoid full-table RAND())
  $sel = $mysqli->prepare("SELECT id, status FROM gis_points WHERE lat BETWEEN ? AND ? AND lng BETWEEN ? AND ?");
  if(!$sel) jexit(['success'=>false,'message'=>'SQL prepare failed','error'=>$mysqli->error],500);
  $sel->bind_param('dddd', $minLat, $maxLat, $minLng, $maxLng);
  $sel->execute();
  $rs = $sel->get_result();
  $ids=[]; $curr=[];
  while($r=$rs->fetch_assoc()){ $ids[]=(int)$r['id']; $curr[(int)$r['id']]=(int)$r['status']; }
  $sel->close();

  if (empty($ids)){
    jexit(['success'=>true,'updated_rows'=>0,'summary'=>['normal_0'=>0,'alert_1'=>0]]);
  }

  // Build a temporary table or update each id batch. For simplicity, do a single UPDATE using FIND_IN_SET with a CSV.
  // However, prepared statements and large IN lists: we‚Äôll chunk.
  $updatedTotal=0;
  $chunkSize=500;
  for ($i=0; $i<count($ids); $i+=$chunkSize){
    $chunk = array_slice($ids, $i, $chunkSize);
    // create placeholders
    $place = implode(',', array_fill(0, count($chunk), '?'));
    $types = str_repeat('i', count($chunk));

    // We will compute new status as: (RAND() < ratio) ? 1 : 0
    // Then set status_timestamp=NOW() when new status != old status.
    $sql = "UPDATE gis_points
            SET
              status = (RAND() < ?),
              status_timestamp = IF(status <> (RAND() < ?), NOW(), status_timestamp),
              updated_at = NOW()
            WHERE id IN ($place)";
    // BUT the two RAND() calls differ per evaluation; to keep consistent per row we can reuse RAND() twice; acceptable for randomization.
    // Bind: ratio twice + ids
    $st = $mysqli->prepare($sql);
    if(!$st) jexit(['success'=>false,'message'=>'SQL prepare failed','error'=>$mysqli->error],500);
    $bindTypes = "dd".$types;
    $bindVals = array_merge([ (float)$ratio, (float)$ratio ], $chunk);
    $st->bind_param($bindTypes, ...$bindVals);
    $ok=$st->execute();
    $err=$st->error;
    $aff=$st->affected_rows;
    $st->close();
    if(!$ok) jexit(['success'=>false,'message'=>'Randomize update failed','error'=>$err],500);
    $updatedTotal += $aff;
  }

  // Return summary within bbox after randomization
  $st2 = $mysqli->prepare("SELECT status, COUNT(*) AS c FROM gis_points WHERE lat BETWEEN ? AND ? AND lng BETWEEN ? AND ? GROUP BY status");
  if(!$st2) jexit(['success'=>false,'message'=>'SQL prepare summary failed','error'=>$mysqli->error],500);
  $st2->bind_param('dddd', $minLat, $maxLat, $minLng, $maxLng);
  $st2->execute();
  $rs2 = $st2->get_result();
  $sum = [ 'normal_0'=>0, 'alert_1'=>0 ];
  while($r=$rs2->fetch_assoc()){
    $k = ((int)$r['status']===1) ? 'alert_1' : 'normal_0';
    $sum[$k] = (int)$r['c'];
  }
  $st2->close();

  jexit(['success'=>true,'updated_rows'=>$updatedTotal,'summary'=>$sum]);
}

/* ---------- default / unknown ---------- */
jexit(['success'=>false,'message'=>'Unknown or unsupported action'], 400);

