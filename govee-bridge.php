<?php
// === Konfiguration ===
$apiKey = "XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX";
$RemoteExecute = false;       // false = nur lokale IPs erlaubt, true = remote erlaubt
$ColorIntIsBGR = false;       // false = RGB (Standard), true = BGR (z.B. DMX)

// === Zugriffsbeschränkung ===
$clientIP = $_SERVER['REMOTE_ADDR'];
$isLocal = false;

if (
    $clientIP === '127.0.0.1' || $clientIP === '::1' ||
    preg_match('/^192\.168\./', $clientIP) ||
    preg_match('/^10\./', $clientIP) ||
    preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $clientIP)
) {
    $isLocal = true;
}

if (!$RemoteExecute && !$isLocal) {
    header("HTTP/1.1 403 Forbidden");
    echo json_encode([
        "error" => "Remote access is not allowed from this IP.",
        "your_ip" => $clientIP
    ]);
    exit;
}

// === Logging ===
file_put_contents("log.txt", date('c') . " - " . $clientIP . " - " . $_SERVER['REQUEST_URI'] . PHP_EOL, FILE_APPEND);

// === Parameter einlesen ===
$device      = $_GET['mac'] ?? null;
$model       = $_GET['model'] ?? null;
$turn        = $_GET['turn'] ?? null;
$brightness  = $_GET['brightness'] ?? null;
$rgb         = $_GET['rgb'] ?? null;
$scene       = $_GET['scene'] ?? null;
$colorTemp   = $_GET['temp'] ?? null;
$colorInt    = $_GET['colorInt'] ?? null;
$statusOnly  = $_GET['status'] ?? null;
$listDevices = $_GET['devices'] ?? null;
$allOn       = $_GET['allon'] ?? null;
$allOff      = $_GET['alloff'] ?? null;

// === MAC formatieren falls nötig ===
if ($device && !str_contains($device, ":")) {
    $device = strtoupper(implode(":", str_split($device, 2)));
}

// === Ergebnis vorbereiten ===
$results = [];

// === Govee API Funktionen ===
function goveeControl($apiKey, $device, $model, $commandName, $commandValue) {
    $url = "https://developer-api.govee.com/v1/devices/control";
    $data = [
        "device" => $device,
        "model" => $model,
        "cmd" => [
            "name" => $commandName,
            "value" => $commandValue
        ]
    ];
    $payload = json_encode($data);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Govee-API-Key: $apiKey"
    ]);

    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function goveeStatus($apiKey, $device, $model) {
    $url = "https://developer-api.govee.com/v1/devices/state?device=$device&model=$model";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Govee-API-Key: $apiKey"
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function goveeDevices($apiKey) {
    $url = "https://developer-api.govee.com/v1/devices";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Govee-API-Key: $apiKey"
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function loxoneToRGB($value) {
    $r = (int)((($value - (int)($value / 1000 + 0.5) * 1000) * 2.55) + 0.5);
    $g = (int)(((int)($value / 1000 + 0.5) - (int)($value / 1000000 + 0.5) * 1000) * 2.55 + 0.5);
    $b = (int)(((int)($value / 1000000 + 0.5)) * 2.55 + 0.5);
    return ["r" => $r, "g" => $g, "b" => $b];
}

// === Geräte-Liste anzeigen
if ($listDevices) {
    $results['devices'] = goveeDevices($apiKey);
}

// === ALL ON / ALL OFF ===
if (($allOn || $allOff) && !$device && !$model) {
    $action = $allOn ? "on" : "off";
    $devices = goveeDevices($apiKey);
    $results['massAction'] = [];
    if (isset($devices['data']['devices'])) {
        foreach ($devices['data']['devices'] as $dev) {
            $results['massAction'][] = [
                "device" => $dev['device'],
                "model" => $dev['model'],
                "result" => goveeControl($apiKey, $dev['device'], $dev['model'], "turn", $action)
            ];
        }
    }
}

// === Status abfragen
if ($statusOnly && $device && $model) {
    $results['status'] = goveeStatus($apiKey, $device, $model);
}

// === Steuerbefehle (wenn MAC + Modell vorhanden)
if ($device && $model) {

    // Ein/Aus
    if ($turn === "on" || $turn === "off") {
        $results['turn'] = goveeControl($apiKey, $device, $model, "turn", $turn);
    }

    // Helligkeit
    if (is_numeric($brightness)) {
        $brightness = max(0, min(100, intval($brightness)));
        $results['brightness'] = goveeControl($apiKey, $device, $model, "brightness", $brightness);
    }

    // Farbsteuerung: entweder direkt per rgb=... oder über colorInt=...
    if ($rgb) {
        $parts = explode(",", $rgb);
        if (count($parts) == 3) {
            $r = max(0, min(255, intval($parts[0])));
            $g = max(0, min(255, intval($parts[1])));
            $b = max(0, min(255, intval($parts[2])));
            $results['color'] = goveeControl($apiKey, $device, $model, "color", ["r" => $r, "g" => $g, "b" => $b]);
        }
    } elseif ($colorInt !== null) {
        $intVal = intval($colorInt);

        if ($intVal < 100000000) {
            $rgbArray = loxoneToRGB($intVal);
            $r = $rgbArray['r'];
            $g = $rgbArray['g'];
            $b = $rgbArray['b'];
            $results['colorInt'] = [
                "input" => $intVal,
                "source" => "loxone",
                "converted" => $rgbArray,
                "response" => goveeControl($apiKey, $device, $model, "color", $rgbArray)
            ];
        } else {
            if ($ColorIntIsBGR) {
                $b = ($intVal >> 16) & 0xFF;
                $g = ($intVal >> 8) & 0xFF;
                $r = $intVal & 0xFF;
            } else {
                $r = ($intVal >> 16) & 0xFF;
                $g = ($intVal >> 8) & 0xFF;
                $b = $intVal & 0xFF;
            }

            $results['colorInt'] = [
                "input" => $intVal,
                "mode" => $ColorIntIsBGR ? "BGR" : "RGB",
                "converted" => ["r" => $r, "g" => $g, "b" => $b],
                "response" => goveeControl($apiKey, $device, $model, "color", ["r" => $r, "g" => $g, "b" => $b])
            ];
        }
    }

    // Szene setzen
    if ($scene) {
        $results['scene'] = goveeControl($apiKey, $device, $model, "scene", $scene);
    }

    // Farbtemperatur
    if (is_numeric($colorTemp)) {
        $colorTemp = max(2000, min(9000, intval($colorTemp)));
        $results['colorTemp'] = goveeControl($apiKey, $device, $model, "colorTem", $colorTemp);
    }
}

// === JSON-Ausgabe
header('Content-Type: application/json');
echo json_encode([
    "status" => "ok",
    "request" => $_GET,
    "results" => $results
], JSON_PRETTY_PRINT);
