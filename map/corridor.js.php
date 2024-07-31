<?php
require_once dirname(__FILE__) . '/../helpers/constants.php';
require_once SITE_DOCUMENT_ROOT . 'helpers/libraries/corridorParser.php';
header('Content-Type: application/javascript');

$parser = new CorridorParser();
$xml = $parser->getReadOnlySimpleXml();

$latMin = null;
$latMax = null;
$lngMin = null;
$lngMax = null;
        
foreach ($xml->xpath('/Corridor/Intersection/TraVisConfiguration/Intersection/@Location') as $location) {
    $parts = explode(',', $location);
    $lat = floatval($parts[0]);
    $lng = floatval($parts[1]);

    if ($latMin == null || $lat < $latMin) {
        $latMin = $lat;
    }
    if ($latMax == null || $lat > $latMax) {
            $latMax = $lat;
    }
    if ($lngMin == null || $lng < $lngMin) {
        $lngMin = $lng;
    }
    if ($lngMax == null || $lng > $lngMax) {
        $lngMax = $lng;
    }

    $latDelta = $latMax - $latMin;
    $lngDelta = $lngMax - $lngMin;

    $latMin -= $latDelta * 0.05;
    $latMax += $latDelta * 0.05;
    $lngMin -= $lngDelta * 0.05;
    $lngMax += $lngDelta * 0.05;

    $latCenter = ($latMin + $latMax) / 2.0;
    $lngCenter = ($lngMin + $lngMax) / 2.0;
    $id = 0;
    $corridor_id = $id++;
}
$json = array();
$json["lat"] = $latCenter;
$json["lng"] = $lngCenter;
$json["latMin"] = $latMin;
$json["latMax"] = $latMax;
$json["lngMin"] = $lngMin;
$json["lngMax"] = $lngMax;
$json["zoom_level"] = 12;
$json_data = array();
$json_corridor = array();
$json_corridor["name"] = "Unnamed Corridor";
$json_corridor["lat"] = $latCenter;
$json_corridor["lng"] = $lngCenter;
$json_corridor["zoom_level"] = 2;
$json_corridor["id"] = $corridor_id;
$json_intersections = array();

$intersection_count = 0;
foreach ($xml->xpath('/Corridor/Intersection') as $intersection) {
    $intersection_id = $id++;
    $location = $intersection->xpath('TraVisConfiguration/Intersection/@Location')[0];
    $parts = explode(',', $location);
    $lat = floatval($parts[0]);
    $lng = floatval($parts[1]);

    $json_intersection = array();
    $json_intersection["name"] = (string)$intersection->xpath("TraVisConfiguration/Intersection/@name")[0];
    $json_intersection["id"] = $intersection_id;
    $json_intersection["processor_ip"] = (string)$intersection["IP"];
    $json_intersection["lat"] = $lat;
    $json_intersection["lng"] = $lng;
    $json_intersection["corridor_id"] = 0;
    $json_intersection["ip_address"] = (string)$intersection['IP'];
    $json_traffic_signals = array();

    $phase_count = 0;
    foreach ($intersection->xpath('TraVisConfiguration/Intersection/Direction/Phases/Phase') as $phase) {
        $phase_id = $id++;
        $intersection_direction = $phase->xpath('ancestor::Direction/@name')[0];
        if ($intersection_direction == 'North') {
            $direction = 'Southbound';
        } else if ($intersection_direction == 'South') {
            $direction = 'Northbound';
        } else if ($intersection_direction == 'East') {
            $direction = 'Westbound';
        } else if ($intersection_direction == 'West') {
            $direction = 'Eastbound';
        } else {
            $direction = 'Unknown';
        }
        $is_turn = intval((string)$phase['name']) % 2 == 1;
        if ($is_turn) {
            $signalName = $direction . " Left Turn";
        } else {
            $signalName = $direction . " Through";
        }
        $location = (string)$phase['Location'];
        $parts = explode(',', $location);
        $lat = floatval($parts[0]);
        $lng = floatval($parts[1]);
        $json_traffic_signal = array(
            "name" => $signalName,
            "id" => $phase_id,
            "lat" => $lat,
            "lng" => $lng,
            "bearing" => intval((string)$phase['Angle']),
            "turn" => $is_turn ? true : false,
            "intersection_id" => $intersection_id,
            "phase" => intval((string)$phase['name']));
        $json_traffic_signals[] = array( "traffic_signal" => $json_traffic_signal );
    }
    $json_intersection["traffic_signals"] = $json_traffic_signals;
    $json_cameras = array();

    $known_cameras = array();
    $stream_count = 0;
    foreach ($intersection->xpath('TraVisConfiguration/VideoStreamSettings/VideoStream') as $videostream) {
        $stream_id = $id++;
        $url = (string)$videostream['Name'];
        $camera_ip = parse_url($url, PHP_URL_HOST);
        $known_cameras[] = (string)$videostream['FriendlyName'];

        $json_camera = array(
            "name" => (string)$videostream['FriendlyName'],
            "id" => $stream_id,
            "raw_url" => $url,
            "ip_address" => $camera_ip,
            "intersection_id" => $intersection_id
        );
        $json_cameras[] = array("camera" => $json_camera);
    }
    foreach ($intersection->xpath('TraVisConfiguration/VideoStreamSettings/VideoServer') as $videoserver) {
        $url = (string)$videoserver['Name'];
        $camera_ip = parse_url($url, PHP_URL_HOST);

        foreach ($videoserver->xpath('View') as $view) {
            if (!in_array((string)$view['Name'], $known_cameras)) {
                $known_cameras[] = (string)$view['Name'];
                $stream_id = $id++;
                $json_camera = array(
                    "name" => (string)$view['Name'],
                    "id" => $stream_id,
                    "raw_url" => $url,
                    "ip_address" => $camera_ip,
                    "intersection_id" => $intersection_id
                );
                $json_cameras[] = array("camera" => $json_camera);
            }
        }
    }
    $json_intersection["cameras"] = $json_cameras;
    $json_intersections[] = array("intersection" => $json_intersection);
}
$json_corridor["intersections"] = $json_intersections;
$json_data["corridor"] = $json_corridor;
$json["data"] = $json_data;
?>
json = <?= json_encode($json) ?>;

json.callback = page.onload;
page.initialize("mapCanvas", json);