<?php
header('Content-Type: application/json');

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define constants
define('CREATE_SCRIPT_URL', 'SCRIPT_URL_SLOT/scripts/create.sh');
define('RELOAD_SCRIPT_URL', 'SCRIPT_URL_SLOT/scripts/reload.sh');
define('CREATE_SCRIPT_PATH', '/var/www/geo-location-api/scripts/create.sh');
define('RELOAD_SCRIPT_PATH', '/var/www/geo-location-api/scripts/reload.sh');

class Api
{
    private $apiKey = 'xxxx';
    private $db;

    public function __construct()
    {
        try {
            $this->connectDatabase();
        } catch (Exception $e) {
            $this->handleError($e->getMessage(), 500);
        }
    }
 
    private function handleError($message, $code = 500)
    {
        error_log("API Error: $message");
        http_response_code($code);
        echo json_encode(['error' => $message]);
        exit;
    }

    private function getPgPassword()
    {
        $command = 'sudo /bin/grep -oP "pg pass: \K\S+" /root/auth.txt';
        $pgPassword = shell_exec($command);
        
        if (!$pgPassword) {
            throw new Exception("Database password not found.");
        }
        
        return trim($pgPassword);
    }

    private function connectDatabase()
    {
        try {
            $pgPassword = $this->getPgPassword();
            $this->db = new PDO(
                'pgsql:host=localhost;dbname=nominatim',
                'postgres',
                $pgPassword,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    private function validateApiKey($key)
    {
        return hash_equals($this->apiKey, $key);
    }

    private function validateCreateInput($data)
    {
        $required = ['name', 'city', 'suburb', 'country', 'latitude', 'longitude'];
        $missing = array_filter($required, fn($field) => !isset($data[$field]) || empty($data[$field]));

        if (!empty($missing)) {
            return ['error' => 'Missing required fields: ' . implode(', ', $missing)];
        }

        if (!is_numeric($data['latitude']) || !is_numeric($data['longitude'])) {
            return ['error' => 'Invalid latitude or longitude format'];
        }

        $lat = floatval($data['latitude']);
        $lon = floatval($data['longitude']);

        if ($lat < -90 || $lat > 90) {
            return ['error' => 'Latitude must be between -90 and 90'];
        }

        if ($lon < -180 || $lon > 180) {
            return ['error' => 'Longitude must be between -180 and 180'];
        }

        return true;
    }

    private function downloadAndPrepareScript($scriptUrl, $scriptPath)
    {
        $script = @file_get_contents($scriptUrl);
        if ($script === false) {
            throw new Exception("Failed to download script from: $scriptUrl");
        }

        if (@file_put_contents($scriptPath, $script) === false) {
            throw new Exception("Failed to save script to: $scriptPath");
        }

        if (!@chmod($scriptPath, 0755)) {
            throw new Exception("Failed to make script executable: $scriptPath");
        }

        return true;
    }

    private function executeReloadScript()
    {
        try {
            $this->downloadAndPrepareScript(RELOAD_SCRIPT_URL, RELOAD_SCRIPT_PATH);
            $command = 'sudo ' . RELOAD_SCRIPT_PATH . ' 2>&1';
            exec($command, $output, $return_code);

            if ($return_code !== 0) {
                throw new Exception("Reload script failed: " . implode("\n", $output));
            }

            @unlink(RELOAD_SCRIPT_PATH);
            return true;
        } catch (Exception $e) {
            error_log("Reload script error: " . $e->getMessage());
            return false;
        }
    }

    private function executeCreateScript($data)
    {
        try {
            $this->downloadAndPrepareScript(CREATE_SCRIPT_URL, CREATE_SCRIPT_PATH);
            $command = $this->buildCommand($data);
            exec($command, $output, $return_code);

            if ($return_code !== 0) {
                throw new Exception("Create script failed: " . implode("\n", $output));
            }

            @unlink(CREATE_SCRIPT_PATH);
            return ['success' => true, 'message' => 'Location created successfully', 'output' => $output];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function buildCommand($data)
    {
        $requiredParams = [
            'name' => '-n',
            'city' => '-c',
            'suburb' => '-s',
            'country' => '-co',
            'latitude' => '-lat',
            'longitude' => '-lon'
        ];

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

        $params = [];

        foreach ($requiredParams as $key => $flag) {
            $value = $data[$key];
            $params[] = $flag . " '" . (in_array($key, ['latitude', 'longitude']) ? 
                floatval($value) : 
                str_replace("'", "'\\''", $value)) . "'";
        }

        foreach ($optionalParams as $key => $param) {
            if (!empty($data[$key])) {
                $params[] = $param . " '" . str_replace("'", "'\\''", $data[$key]) . "'";
            }
        }

        return 'sudo ' . CREATE_SCRIPT_PATH . ' ' . implode(' ', $params) . ' 2>&1';
    }

    public function create($data)
    {
        if (!$this->validateApiKey($data['api_key'])) {
            return json_encode(['error' => 'Invalid API key']);
        }

        $validation = $this->validateCreateInput($data);
        if (is_array($validation)) {
            return json_encode($validation);
        }

        $result = $this->executeCreateScript($data);
        if ($result['success']) {
            $this->executeReloadScript();
        }

        return json_encode($result);
    }

    private function validateUpdateInput($data)
    {
        $requiredParams = [
            'osm_id' => 'osm_id',
        ];

        foreach ($requiredParams as $key => $param) {
            if (!isset($data[$key]) || empty($data[$key])) {
                return ['error' => "Missing required parameter: $param"];
            }
        }

        return true;
    }

    public function update($data)
    {
        if (!$this->validateApiKey($data['api_key'])) {
            return json_encode(['error' => 'Invalid API key']);
        }

        $validation = $this->validateUpdateInput($data);

        if (is_array($validation)) {
            return json_encode($validation);
        }

        try {
            $this->db->beginTransaction();

            $address = json_encode([
                'city' => $data['city'],
                'state' => $data['suburb'],
                'street' => $data['street'] ?? '',
                'suburb' => $data['suburb'],
                'postcode' => $data['postcode'] ?? '',
                'housenumber' => $data['house_number'] ?? ''
            ]);

            $extratags = json_encode($data['extratags'] ?? []);
            
            $tables = ['placex', 'place'];
            foreach ($tables as $table) {
                $stmt = $this->db->prepare("
                    UPDATE public.{$table} 
                    SET name = :name, 
                        address = :address, 
                        extratags = :extratags 
                    WHERE osm_id = :osm_id
                ");

                $stmt->execute([
                    'name' => $data['name'],
                    'address' => $address,
                    'extratags' => $extratags,
                    'osm_id' => $data['osm_id']
                ]);
            }

            $this->db->commit();
            $this->executeReloadScript();
            
            return json_encode(['success' => true, 'message' => 'Item updated successfully']);
        } catch (Exception $e) {
            $this->db->rollBack();
            return json_encode(['error' => 'Update failed: ' . $e->getMessage()]);
        }
    }

    public function delete($data)
    {
        if (!$this->validateApiKey($data['api_key'])) {
            return json_encode(['error' => 'Invalid API key']);
        }

        try {
            $this->db->beginTransaction();

            $tables = ['placex', 'place'];
            foreach ($tables as $table) {
                $stmt = $this->db->prepare("DELETE FROM public.{$table} WHERE osm_id = :osm_id");
                $stmt->execute(['osm_id' => $data['osm_id']]);
            }

            $this->db->commit();
            $this->executeReloadScript();
            
            return json_encode(['success' => true, 'message' => 'Item deleted successfully']);
        } catch (Exception $e) {
            $this->db->rollBack();
            return json_encode(['error' => 'Delete failed: ' . $e->getMessage()]);
        }
    }

    public function handleRequest()
    {
        $headers = getallheaders();
        if (!isset($headers['Api-Key'])) {
            $this->handleError('API key required', 401);
        }

        $method = $_SERVER['REQUEST_METHOD'];
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->handleError('Invalid JSON data', 400);
        }

        switch ($method) {
            case 'POST':
                echo $this->create($data);
                break;
            case 'PUT':
                echo $this->update($data);
                break;
            case 'DELETE':
                echo $this->delete($data);
                break;
            default:
                $this->handleError('Method not allowed', 405);
        }
    }
}

$api = new Api();
$api->handleRequest();