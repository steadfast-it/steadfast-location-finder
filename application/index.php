<?php
header('Content-Type: application/json');

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define constants
define('CREATE_SCRIPT_URL', 'http://localhost:9797/scripts/create.sh');
define('RELOAD_SCRIPT_URL', 'http://localhost:9797/scripts/reload.sh');
define('CREATE_SCRIPT_PATH', '/var/www/geo-location-api/scripts/create.sh');
define('RELOAD_SCRIPT_PATH', '/var/www/geo-location-api/scripts/reload.sh');
define('API_KEY', 'xxxx');


class Api
{
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
        http_response_code($code);
        echo json_encode(['error' => $message]);
        exit;
    }

    private function getPgPassword()
    {
        return "password";
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

    private function validateApiKey($headers)
    {
        if (!isset($headers['X-API-Key']) || $headers['X-API-Key'] !== API_KEY) {
            $this->handleError('Invalid API key', 401);
            return false;
        }
        return true;
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

            return true;
        } catch (Exception $e) {
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
        $headers = getallheaders();
        if (!$this->validateApiKey($headers)) {
            return json_encode(['error' => 'Invalid API key']);
        }

        $validation = $this->validateCreateInput($data);
        if (is_array($validation)) {
            return json_encode($validation);
        }

        $result = $this->executeCreateScript($data);
        if (isset($result['success']) && $result['success']) {
            $this->executeReloadScript();
        }

        return json_encode($result);
    }

    private function validateUpdateInput($data)
    {
        if (!isset($data['osm_id']) || empty($data['osm_id'])) {
            return ['error' => 'Missing required parameter: osm_id'];
        }
        return true;
    }

    private function getDataByOsmId($osm_id)
    {
        $stmt = $this->db->prepare("SELECT * FROM public.place WHERE osm_id = :osm_id");
        $stmt->execute(['osm_id' => $osm_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $result['name'] = $this->decodeHstore($result['name']);
            $result['address'] = $this->decodeHstore($result['address']);
            $result['extratags'] = $this->decodeHstore($result['extratags']);
        }
        return $result;
    }

    private function decodeHstore($hstore)
    {
        if (!$hstore) return [];

        $pairs = explode(',', $hstore);
        $decoded = [];

        foreach ($pairs as $pair) {

            if (strpos($pair, '=>') === false) continue;

            [$key, $value] = explode('=>', $pair, 2);
            $decoded[trim($key, '"')] = isset($value) ? trim($value, '"') : null;
        }

        return $decoded;
    }


    public function update($data)
    {
        $headers = getallheaders();

        // Validate API key
        if (!$this->validateApiKey($headers)) {
            return json_encode(['success' => false, 'error' => 'Invalid API key']);
        }

        // Validate input data
        $validation = $this->validateUpdateInput($data);
        if (is_array($validation)) {
            return json_encode(['success' => false, 'errors' => $validation]);
        }

        try {
            // Retrieve existing data by OSM ID
            $previousData = $this->getDataByOsmId($data['osm_id']);
            if (!$previousData) {
                return json_encode(['success' => false, 'error' => 'Location not found']);
            }


            $previousName = $previousData['name'];
            $previousAddress = $previousData['address'];
            $previousExtraTags = $previousData['extratags'];


            $this->db->beginTransaction();


            $nameFields = array_filter([
                'name' => $data['name'] ?? $previousName['name'] ?? '',
                'name:en' => $data['english_name'] ?? $previousName['name:en'] ?? '',
                'name:bn' => $data['bangla_name'] ?? $previousName['name:bn'] ?? $data['name'],
            ], fn($value) => !empty($value));


            $addressFields = array_filter([
                'city' => $data['city'] ?? $previousAddress['city'] ?? '',
                'state' => $data['state'] ?? $previousAddress['state'] ?? '',
                'street' => $data['street'] ?? $previousAddress['street'] ?? '',
                'suburb' => $data['suburb'] ?? $previousAddress['suburb'] ?? '',
                'country' => $data['country_code'] ?? $previousAddress['country_code'] ?? '',
                'postcode' => $data['postcode'] ?? $previousAddress['postcode'] ?? '',
                'housenumber' => $data['house_number'] ?? $previousAddress['housenumber'] ?? '',
            ], fn($value) => !empty($value));


            $extraTags = array_filter([

                'email' => $data['extratags']['email'] ?? $previousExtraTags['email'] ?? '',
                'phone' => $data['extratags']['phone'] ?? $previousExtraTags['phone'] ?? '',
                'company' => $data['extratags']['company'] ?? $previousExtraTags['company'] ?? '',
                'website' => $data['extratags']['website'] ?? $previousExtraTags['website'] ?? '',
                'building' => $data['extratags']['building'] ?? $previousExtraTags['building'] ?? '',
                'capacity' => $data['extratags']['capacity'] ?? $previousExtraTags['capacity'] ?? '',
                'industry' => $data['extratags']['industry'] ?? $previousExtraTags['industry'] ?? '',
                'employees' => $data['extratags']['employees'] ?? $previousExtraTags['employees'] ?? '',
                'maintainer' => $data['extratags']['maintainer'] ?? $previousExtraTags['maintainer'] ?? '',
                'wheelchair' => $data['extratags']['wheelchair'] ?? $previousExtraTags['wheelchair'] ?? '',
                'opening_hours' => $data['extratags']['opening_hours'] ?? $previousExtraTags['opening_hours'] ?? '',
                'amenity:parking' => $data['extratags']['amenity:parking'] ?? $previousExtraTags['amenity:parking'] ?? '',
                'building:levels' => $data['extratags']['building:levels'] ?? $previousExtraTags['building:levels'] ?? '',
                'internet' => $data['extratags']['internet_access'] ?? $previousExtraTags['internet_access'] ?? '',
                'maintainer:email' => $data['extratags']['maintainer:email'] ?? $previousExtraTags['maintainer:email'] ?? ''
            ], fn($value) => !empty($value));


            $tables = ['placex', 'place'];

            foreach ($tables as $table) {
                $stmt = $this->db->prepare("
                    UPDATE public.{$table} 
                    SET name = :name, 
                        address = :address::hstore, 
                        extratags = :extratags::hstore 
                    WHERE osm_id = :osm_id
                ");

                $stmt->execute([
                    'name' => $this->formatHstore($nameFields),
                    'address' => $this->formatHstore($addressFields),
                    'extratags' => $this->formatHstore($extraTags),
                    'osm_id' => $data['osm_id']
                ]);
            }


            // $stmt = $this->db->prepare("
            //     UPDATE search_name 
            //     SET name_vector = int4vector('simple', :name_text)
            //     WHERE place_id = (
            //         SELECT place_id FROM placex WHERE osm_id = :osm_id
            //     )
            // ");

            // $stmt->execute([
            //     'name_text' => $data['name'],
            //     'osm_id' => $data['osm_id']
            // ]);

            // if (!empty($addressFields)) {
            //     $stmt = $this->db->prepare("
            //         UPDATE place_addressline pa
            //         SET address = :address::hstore
            //         WHERE pa.place_id = (
            //             SELECT place_id FROM placex WHERE osm_id = :osm_id
            //         )
            //     ");
            //     $stmt->execute([
            //         'address' => $this->formatHstore($addressFields),
            //         'osm_id' => $data['osm_id']
            //     ]);
            // }

            // // Update location_property if relevant
            // if (!empty($extraTags)) {
            //     $stmt = $this->db->prepare("
            //         UPDATE location_property_osmline lp
            //         SET indexed_status = 2,
            //             indexed_date = NOW()
            //         WHERE place_id = (
            //             SELECT place_id FROM placex WHERE osm_id = :osm_id
            //         )
            //     ");
            //     $stmt->execute(['osm_id' => $data['osm_id']]);
            // }

            // $stmt = $this->db->prepare("
            //     WITH words AS (
            //         SELECT word_id, word_token
            //         FROM word
            //         WHERE word_token = ANY(
            //             regexp_split_to_array(lower(:name_text), E'\\\\s+')
            //         )
            //     )
            //     UPDATE search_name_words snw
            //     SET word_id = w.word_id
            //     FROM words w
            //     WHERE snw.place_id = (
            //         SELECT place_id FROM placex WHERE osm_id = :osm_id
            //     )
            // ");
            // $stmt->execute([
            //     'name_text' => $data['name'],
            //     'osm_id' => $data['osm_id']
            // ]);

            $this->db->commit();

            $this->executeReloadScript();

            return json_encode([
                'success' => true,
                'message' => 'Item updated successfully',
                'updated_data' => [
                    'name' => $nameFields,
                    'address' => $addressFields,
                    'extratags' => $extraTags
                ]
            ]);
        } catch (Exception $e) {

            $this->db->rollBack();
            return json_encode([
                'success' => false,
                'error' => 'Update failed',
                'details' => $e->getMessage()
            ]);
        }
    }

    private function formatHstore($data)
    {
        if (empty($data)) return null;
        return implode(',', array_map(
            fn($key, $value) => '"' . addslashes($key) . '"=>"' . addslashes($value) . '"',
            array_keys($data),
            $data
        ));
    }

    public function delete($data)
    {
        $headers = getallheaders();
        if (!$this->validateApiKey($headers)) {
            return json_encode(['error' => 'Invalid API key']);
        }

        if (!isset($data['osm_id']) || empty($data['osm_id'])) {
            return json_encode(['error' => 'Missing required parameter: osm_id']);
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
