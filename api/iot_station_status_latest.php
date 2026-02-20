<?php
/**
 * /opt/lampp/htdocs/api/iot/iot_station_status_latest.php
 * Returns the latest status per station_id from iot_station_status
 * Optional filters:
 *   - station_id   (int or CSV "1,2,3")
 *   - since        (ISO8601 or "YYYY-MM-DD HH:MM:SS") -> only rows newer than this
 *   - limit        (int) max rows to return (after grouping by station)
 *
 * Response:
 * {
 *   "success": true,
 *   "data": [
 *     {"station_id":1,"station_name":"X","status":0,"temp":40.73,"humid":58.37,"timestamp":"2025-11-05 11:22:19"}
 *   ]
 * }
 *
 * Confidential ‚Äì Internal Use Only
 */
header("Content-Type: application/json; charset=UTF-8");
// error_reporting(E_ALL); ini_set('display_errors', 1);

require_once __DIR__ . '/db.php';   // must define $mysqli
$mysqli->set_charset('utf8mb4');
date_default_timezone_set('Asia/Bangkok');

function jexit($arr, $code=200){
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  if (isset($GLOBALS['mysqli']) && $GLOBALS['mysqli'] instanceof mysqli) $GLOBALS['mysqli']->close();
  exit;
}
function in_any($key, $default=null){
  if (isset($_GET[$key]))  return is_string($_GET[$key])  ? trim($_GET[$key])  : $_GET[$key];
  if (isset($_POST[$key])) return is_string($_POST[$key]) ? trim($_POST[$key]) : $_POST[$key];
  $raw = file_get_contents('php://input');
  if ($raw) {
    $js = json_decode($raw, true);
    if (is_array($js) && array_key_exists($key, $js)) {
      return is_string($js[$key]) ? trim($js[$key]) : $js[$key];
    }
  }
  return $default;
}

$station_param = in_any('station_id', '');
$since         = in_any('since', '');
$limit         = (int) (in_any('limit', 0));
$limit         = max(0, min(1000, $limit));

$ids = [];
if ($station_param !== '') {
  foreach (explode(',', $station_param) as $p) {
    $v = (int)trim($p);
    if ($v > 0) $ids[] = $v;
  }
  $ids = array_values(array_unique($ids));
}

$where = " WHERE 1=1 ";
$types = "";
$params = [];

if (!empty($ids)) {
  $place = implode(',', array_fill(0, count($ids), '?'));
  $where .= " AND s.station_id IN ($place) ";
  $types .= str_repeat('i', count($ids));
  array_push($params, ...$ids);
}
if ($since !== '') {
  // best-effort parse
  $ts = strtotime($since);
  if ($ts === false) {
    jexit(['success'=>false,'message'=>'Bad "since"'], 400);
  }
  $since_sql = date('Y-m-d H:i:s', $ts);
  $where .= " AND s.`timestamp` >= ? ";
  $types .= "s";
  $params[] = $since_sql;
}

/**
 * We want latest row per station_id. Use a subquery for max(timestamp) per station (after optional filters, except we can‚Äôt put filters inside and outside inconsistently).
 * Strategy: compute latest timestamp per station from *all* rows (optionally filtered by since), then join.
 */
$sql = "
  SELECT s.station_id, s.station_name, s.status1,s.status2, s.temp, s.humid, DATE_FORMAT(s.`timestamp`, '%Y-%m-%d %H:%i:%s') AS `timestamp`
  FROM iot_station_status s
  INNER JOIN (
    SELECT station_id, MAX(`timestamp`) AS max_ts
    FROM iot_station_status
    " . ($since !== '' ? " WHERE `timestamp` >= ?" : "") . "
    GROUP BY station_id
  ) t ON t.station_id = s.station_id AND t.max_ts = s.`timestamp`
  " . (!empty($ids) ? " WHERE s.station_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ") " : "") . "
  ORDER BY s.station_id ASC
  " . ($limit > 0 ? " LIMIT ".$limit : "") . "
";

$bindTypes = "";
$bindVals  = [];
if ($since !== '') { $bindTypes .= "s"; $bindVals[] = $since_sql; }
if (!empty($ids)) { $bindTypes .= str_repeat('i', count($ids)); array_push($bindVals, ...$ids); }

$st = $mysqli->prepare($sql);
if (!$st) jexit(['success'=>false,'message'=>'SQL prepare failed','error'=>$mysqli->error], 500);
if ($bindTypes !== "") $st->bind_param($bindTypes, ...$bindVals);
$st->execute();
$rs = $st->get_result();

$data = [];
while ($r = $rs->fetch_assoc()) {
  $data[] = [
    'station_id'   => (int)$r['station_id'],
    'station_name' => ($r['station_name'] ?? null),
    'status1'       => isset($r['status1']) ? (int)$r['status1'] : null,
    'status2'       => isset($r['status2']) ? (int)$r['status2'] : null,
    'temp'         => isset($r['temp'])   ? (float)$r['temp'] : null,
    'humid'        => isset($r['humid'])  ? (float)$r['humid'] : null,
    'timestamp'    => $r['timestamp']
  ];
}
$st->close();

jexit(['success'=>true,'data'=>$data]);

