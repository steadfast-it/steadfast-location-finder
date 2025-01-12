<?php
header('Content-Type: application/json');

// Enable error logging
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

define('SCRIPT_URL', 'SCRIPT_URL_SLOT/scripts/create.sh');
define('SCRIPT_PATH', '/var/www/geo-location-api/scripts/create.sh');
define('API_KEY', 'XXXX');

function validateApiKey($headers) {
    if (!isset($headers['X-API-Key']) || $headers['X-API-Key'] !== API_KEY) {
        http_response_code(401);
        return ['error' => 'Invalid API key'];
    }
    return true;
}

function validateInput($data) {
    $required = ['name', 'city', 'suburb', 'country', 'latitude', 'longitude'];
    $missing = [];
    
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        return ['error' => 'Missing required fields: ' . implode(', ', $missing)];
    }
    
    
    if (!is_numeric($data['latitude']) || !is_numeric($data['longitude'])) {
        return ['error' => 'Invalid latitude or longitude format'];
    }
    
    
    if ($data['latitude'] < -90 || $data['latitude'] > 90) {
        return ['error' => 'Latitude must be between -90 and 90'];
    }
    

    if ($data['longitude'] < -180 || $data['longitude'] > 180) {
        return ['error' => 'Longitude must be between -180 and 180'];
    }
    
    return true;
}

function downloadScript() {
    $script = file_get_contents(SCRIPT_URL);
    if ($script === false) {
        error_log("Failed to download script from: " . SCRIPT_URL);
        return ['error' => 'Failed to download script from GitHub'];
    }
    
    if (file_put_contents(SCRIPT_PATH, $script) === false) {
        error_log("Failed to save script to: " . SCRIPT_PATH);
        return ['error' => 'Failed to save script'];
    }
    
   
    if (!chmod(SCRIPT_PATH, 0755)) {
        error_log("Failed to make script executable: " . SCRIPT_PATH);
        return ['error' => 'Failed to make script executable'];
    }
    
    return true;
}

function buildCommand($data) {
    $params = [];
    
    $requiredParams = [
        'name' => '-n',
        'city' => '-c',
        'suburb' => '-s',
        'country' => '-co',
        'latitude' => '-lat',
        'longitude' => '-lon'
    ];
    
    foreach ($requiredParams as $key => $flag) {
        $value = $data[$key];

        if (in_array($key, ['latitude', 'longitude'])) {
            $params[] = $flag . " " . floatval($value);
        } else {
            $params[] = $flag . " '" . str_replace("'", "'\\''", $value) . "'";
        }
    }
    
    $optionalParams = [
        'english_name' => '--english-name',
        'website' => '--website',
        'house_number' => '--house-number',
        'street' => '--street',
        'postcode' => '--postcode',
        'state' => '--state',
        'country_code' => '--country-code',
        'phone' => '--phone',
        'email' => '--email',
        'hours' => '--hours',
        'wheelchair' => '--wheelchair',
        'floors' => '--floors',
        'capacity' => '--capacity',
        'parking' => '--parking',
        'internet' => '--internet',
        'company' => '--company',
        'employees' => '--employees',
        'creator' => '--creator',
        'maintainer' => '--maintainer',
        'maintainer_email' => '--maintainer-email'
    ];
    

    foreach ($optionalParams as $key => $param) {
        if (isset($data[$key]) && $data[$key] !== '') {
          
            $value = str_replace("'", "'\\''", $data[$key]);
            $params[] = $param . " '" . $value . "'";
        }
    }
    

    error_log("Generated command parameters: " . implode(' ', $params));
    
    $command = 'sudo ' . SCRIPT_PATH . ' ' . implode(' ', $params) . ' 2>&1';
    error_log("Full command: " . $command);
    return $command;
}


function executeScript($data) {
    $command = buildCommand($data);
    exec($command, $output, $return_code);
    
    
    error_log("Script output: " . implode("\n", $output));
    error_log("Return code: " . $return_code);
    
    
    if (file_exists(SCRIPT_PATH)) {
        unlink(SCRIPT_PATH);
    }
    
    if ($return_code !== 0) {
        return [
            'error' => 'Script execution failed',
            'details' => implode("\n", $output),
            'code' => $return_code
        ];
    }
    
    return [
        'success' => true,
        'message' => 'Location added successfully',
        'output' => $output
    ];
}


function handleRequest() {
   
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        return ['error' => 'Method not allowed'];
    }
    

    $headers = getallheaders();
    

    $keyValidation = validateApiKey($headers);
    if (is_array($keyValidation)) {
        return $keyValidation;
    }
    

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        return ['error' => 'Invalid JSON data'];
    }
    

    error_log("Received input: " . json_encode($input));
    

    $validation = validateInput($input);
    if (is_array($validation)) {
        http_response_code(400);
        return $validation;
    }
    
   
    $download = downloadScript();
    if (is_array($download)) {
        http_response_code(500);
        return $download;
    }
    

    return executeScript($input);
}


$response = handleRequest();
echo json_encode($response, JSON_PRETTY_PRINT);
?>