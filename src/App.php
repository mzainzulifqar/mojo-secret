<?php
namespace Infisical\CliTool;

final class App
{
    public static function run(array $argv): int
    {
        self::loadEnv();
        array_shift($argv); // remove script name
        $cmd = $argv[0] ?? 'help';

        switch ($cmd) {
            case 'call':
                $url   = self::opt($argv, 'url', 'https://httpbin.org/post');
                $note  = self::opt($argv, 'note', 'hello');
                $token = getenv('MYTOOL_TOKEN') ?: self::opt($argv, 'token');

                [$code, $body] = self::httpPost($url, ['note'=>$note], $token);
                echo "HTTP $code\n$body\n";
                return ($code >= 200 && $code < 300) ? 0 : 1;

            case 'infisical-secrets':
                return self::infisicalSecrets();

            case 'sync':
                return self::syncSecrets();

            case 'create-project':
                return self::createProject($argv);

            case 'push-secrets':
                return self::pushSecrets();

            case 'help':
            default:
                echo "Usage:\n";
                echo "  mytool help\n";
                echo "  mytool call --url=https://api.example.com/do --note=hi [--token=XYZ]\n";
                echo "  mytool infisical-secrets\n";
                echo "  mytool sync\n";
                echo "  mytool create-project --name=\"Project Name\"\n";
                echo "  mytool push-secrets\n";
                return 0;
        }
    }

    private static function opt(array $argv, string $key, $default=null) {
        foreach ($argv as $a) {
            if (str_starts_with($a, "--$key=")) {
                return substr($a, strlen("--$key="));
            }
        }
        return $default;
    }

    private static function httpPost(string $url, array $data, ?string $token): array {
        $ch = curl_init($url);
        $headers = ['Content-Type: application/json'];
        if ($token) $headers[] = "Authorization: Bearer $token";
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);
        $res = curl_exec($ch);
        if ($res === false) {
            $err = curl_error($ch);
            curl_close($ch);
            fwrite(STDERR, "cURL error: $err\n");
            return [0, ""];
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$code, $res];
    }

    private static function httpGet(string $url, ?string $token): array {
        $ch = curl_init($url);
        $headers = ['Content-Type: application/json'];
        if ($token) $headers[] = "Authorization: Bearer $token";
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);
        $res = curl_exec($ch);
        if ($res === false) {
            $err = curl_error($ch);
            curl_close($ch);
            fwrite(STDERR, "cURL error: $err\n");
            return [0, ""];
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$code, $res];
    }

    private static function loadEnv(): void {
        $envFile = __DIR__ . '/../.env';
        if (!file_exists($envFile)) {
            return;
        }
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            list($name, $value) = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value);
            putenv(trim($name) . '=' . trim($value));
        }
    }

    private static function getProjectConfig(): ?array {
        if (!file_exists('.infisical.json')) {
            return null;
        }
        $config = json_decode(file_get_contents('.infisical.json'), true);
        return $config ?: null;
    }

    private static function authenticate(): ?string {
        $clientId = getenv('INFISICAL_CLIENT_ID');
        $clientSecret = getenv('INFISICAL_CLIENT_SECRET');
        $apiUrl = getenv('INFISICAL_API_URL');

        if (!$clientId || !$clientSecret || !$apiUrl) {
            fwrite(STDERR, "Error: INFISICAL_CLIENT_ID, INFISICAL_CLIENT_SECRET, and INFISICAL_API_URL must be set in .env\n");
            return null;
        }

        $loginUrl = $apiUrl . '/api/v1/auth/universal-auth/login';
        $loginData = [
            'clientId' => $clientId,
            'clientSecret' => $clientSecret
        ];

        [$loginCode, $loginBody] = self::httpPost($loginUrl, $loginData, null);

        if ($loginCode < 200 || $loginCode >= 300) {
            fwrite(STDERR, "Authentication failed: HTTP $loginCode\n$loginBody\n");
            return null;
        }

        $authData = json_decode($loginBody, true);
        if (!isset($authData['accessToken'])) {
            fwrite(STDERR, "Error: Invalid auth response.\n");
            return null;
        }

        return $authData['accessToken'];
    }


    private static function infisicalSecrets(): int {
        echo "Authenticating with Infisical...\n";
        $token = self::authenticate();
        if (!$token) {
            return 1;
        }
        echo "✓ Authentication successful\n";

        $apiUrl = getenv('INFISICAL_API_URL');

        $projectConfig = self::getProjectConfig();
        if (!$projectConfig || !isset($projectConfig['projectId'])) {
            fwrite(STDERR, "Error: No project configuration found. Run 'mytool create-project' first.\n");
            return 1;
        }
        $projectId = $projectConfig['projectId'];
        $env = $projectConfig['environment'] ?: 'dev';

        $url = $apiUrl . "/api/v4/secrets?projectId=$projectId&environment=$env&secretPath=/&viewSecretValue=true";

        [$code, $body] = self::httpGet($url, $token);

        if ($code >= 200 && $code < 300) {
            $secretsData = json_decode($body, true);
            if (!isset($secretsData['secrets']) || !is_array($secretsData['secrets'])) {
                fwrite(STDERR, "Error: Invalid secrets format received from API\n");
                return 1;
            }

            // Create fresh .env file with Infisical secrets
            $envContent = "# Generated by Infisical CLI Tool\n";
            $envContent .= "# Infisical Configuration\n";
            $envContent .= "INFISICAL_CLIENT_ID=" . getenv('INFISICAL_CLIENT_ID') . "\n";
            $envContent .= "INFISICAL_CLIENT_SECRET=" . getenv('INFISICAL_CLIENT_SECRET') . "\n";
            $envContent .= "INFISICAL_API_URL=" . getenv('INFISICAL_API_URL') . "\n";
            $envContent .= "INFISICAL_PROJECT_ID=" . getenv('INFISICAL_PROJECT_ID') . "\n";
            $envContent .= "INFISICAL_ENV=" . getenv('INFISICAL_ENV') . "\n";
            $envContent .= "\n# Application Secrets\n";

            $secretCount = 0;
            foreach ($secretsData['secrets'] as $secret) {
                if (isset($secret['secretKey']) && isset($secret['secretValue'])) {
                    $key = $secret['secretKey'];
                    $value = $secret['secretValue'];
                    $envContent .= "$key=$value\n";
                    $secretCount++;
                }
            }

            file_put_contents('.env', $envContent, LOCK_EX);
            echo "Fresh .env file created with $secretCount secrets from Infisical!\n";
            return 0;
        } else {
            echo "Failed to retrieve secrets: HTTP $code\n$body\n";
            return 1;
        }
    }

    private static function syncSecrets(): int {
        echo "Starting Infisical sync...\n";

        // Step 1: Login
        echo "1. Authenticating with Infisical...\n";
        $clientId = getenv('INFISICAL_CLIENT_ID');
        $clientSecret = getenv('INFISICAL_CLIENT_SECRET');
        $apiUrl = getenv('INFISICAL_API_URL');

        if (!$clientId || !$clientSecret) {
            fwrite(STDERR, "Error: INFISICAL_CLIENT_ID and INFISICAL_CLIENT_SECRET must be set in .env\n");
            return 1;
        }

        $loginUrl = $apiUrl . '/api/v1/auth/universal-auth/login';
        $loginData = [
            'clientId' => $clientId,
            'clientSecret' => $clientSecret
        ];

        [$loginCode, $loginBody] = self::httpPost($loginUrl, $loginData, null);

        if ($loginCode < 200 || $loginCode >= 300) {
            echo "Authentication failed: HTTP $loginCode\n$loginBody\n";
            return 1;
        }

        $authData = json_decode($loginBody, true);
        if (!isset($authData['accessToken'])) {
            fwrite(STDERR, "Error: Invalid auth response. Please check credentials.\n");
            return 1;
        }

        $token = $authData['accessToken'];
        echo "✓ Authentication successful\n";

        // Step 2: Fetch secrets
        echo "2. Fetching secrets...\n";
        $projectConfig = self::getProjectConfig();
        if (!$projectConfig || !isset($projectConfig['projectId'])) {
            fwrite(STDERR, "Error: No project configuration found. Run 'mytool create-project' first.\n");
            return 1;
        }
        $projectId = $projectConfig['projectId'];
        $env = $projectConfig['environment'] ?: 'dev';

        $secretsUrl = $apiUrl . "/api/v4/secrets?projectId=$projectId&environment=$env&secretPath=/&viewSecretValue=true";
        [$secretsCode, $secretsBody] = self::httpGet($secretsUrl, $token);

        if ($secretsCode < 200 || $secretsCode >= 300) {
            echo "Failed to retrieve secrets: HTTP $secretsCode\n$secretsBody\n";
            return 1;
        }

        $secretsData = json_decode($secretsBody, true);
        if (!isset($secretsData['secrets']) || !is_array($secretsData['secrets'])) {
            fwrite(STDERR, "Error: Invalid secrets format received from API\n");
            return 1;
        }

        echo "✓ Secrets retrieved successfully\n";

        // Step 3: Create fresh .env
        echo "3. Creating fresh .env file...\n";
        $envContent = "# Generated by Infisical CLI Tool\n";
        $envContent .= "# Infisical Configuration\n";
        $envContent .= "INFISICAL_CLIENT_ID=$clientId\n";
        $envContent .= "INFISICAL_CLIENT_SECRET=$clientSecret\n";
        $envContent .= "INFISICAL_API_URL=$apiUrl\n";
        // Note: Project ID is now stored in .infisical.json, not .env
        $envContent .= "INFISICAL_ENV=$env\n";
        $envContent .= "\n# Application Secrets\n";

        $secretCount = 0;
        foreach ($secretsData['secrets'] as $secret) {
            if (isset($secret['secretKey']) && isset($secret['secretValue'])) {
                $key = $secret['secretKey'];
                $value = $secret['secretValue'];
                $envContent .= "$key=$value\n";
                $secretCount++;
            }
        }

        file_put_contents('.env', $envContent, LOCK_EX);
        echo "✓ Fresh .env file created with $secretCount secrets\n";
        echo "\nSync completed successfully!\n";

        return 0;
    }

    private static function createProject(array $argv): int {
        echo "Creating new Infisical project...\n";

        // Get project name from command line
        $projectName = self::opt($argv, 'name');
        if (!$projectName) {
            fwrite(STDERR, "Error: Project name is required. Use --name=\"Project Name\"\n");
            return 1;
        }

        // Step 1: Login to get access token
        echo "1. Authenticating with Infisical...\n";
        $clientId = getenv('INFISICAL_CLIENT_ID');
        $clientSecret = getenv('INFISICAL_CLIENT_SECRET');
        $apiUrl = getenv('INFISICAL_API_URL');

        if (!$clientId || !$clientSecret || !$apiUrl) {
            fwrite(STDERR, "Error: INFISICAL_CLIENT_ID, INFISICAL_CLIENT_SECRET, and INFISICAL_API_URL must be set in .env\n");
            return 1;
        }

        $loginUrl = $apiUrl . '/api/v1/auth/universal-auth/login';
        $loginData = [
            'clientId' => $clientId,
            'clientSecret' => $clientSecret
        ];

        [$loginCode, $loginBody] = self::httpPost($loginUrl, $loginData, null);

        if ($loginCode < 200 || $loginCode >= 300) {
            echo "Authentication failed: HTTP $loginCode\n$loginBody\n";
            return 1;
        }

        $authData = json_decode($loginBody, true);
        if (!isset($authData['accessToken'])) {
            fwrite(STDERR, "Error: Invalid auth response.\n");
            return 1;
        }

        $token = $authData['accessToken'];
        echo "✓ Authentication successful\n";

        // Step 2: Create project
        echo "2. Creating project '$projectName'...\n";
        $slug = strtolower(str_replace([' ', '_'], '-', $projectName));

        // Ensure slug is at least 5 characters
        if (strlen($slug) < 5) {
            $slug .= '-proj';
        }

        $createUrl = $apiUrl . '/api/v1/projects';
        $createData = [
            'projectName' => $projectName,
            'projectDescription' => 'Demo project setup via script',
            'slug' => $slug,
            'template' => 'default',
            'type' => 'secret-manager',
            'shouldCreateDefaultEnvs' => true
        ];

        [$createCode, $createBody] = self::httpPost($createUrl, $createData, $token);

        if ($createCode < 200 || $createCode >= 300) {
            echo "Project creation failed: HTTP $createCode\n$createBody\n";
            return 1;
        }

        $projectData = json_decode($createBody, true);

        if (isset($projectData['project']['id'])) {
            $projectId = $projectData['project']['id'];
        } elseif (isset($projectData['id'])) {
            $projectId = $projectData['id'];
        } else {
            fwrite(STDERR, "Error: Invalid project creation response.\n");
            return 1;
        }

        echo "✓ Project created successfully!\n";
        echo "Project ID: $projectId\n";
        echo "Project Slug: $slug\n";

        // Save project configuration to .infisical.json
        echo "3. Saving project configuration...\n";
        $infisicalConfig = [
            'projectId' => $projectId,
            'projectName' => $projectName,
            'slug' => $slug,
            'environment' => getenv('INFISICAL_ENV') ?: 'dev',
            'apiUrl' => $apiUrl,
            'createdAt' => date('c')
        ];
        file_put_contents('.infisical.json', json_encode($infisicalConfig, JSON_PRETTY_PRINT), LOCK_EX);
        echo "✓ Project configuration saved to .infisical.json\n";

        echo "\nProject creation completed successfully!\n";
        return 0;
    }

    private static function pushSecrets(): int {
        echo "Pushing secrets to Infisical...\n";

        // Step 1: Check if .env exists and parse it
        if (!file_exists('.env')) {
            fwrite(STDERR, "Error: .env file not found.\n");
            return 1;
        }

        echo "1. Parsing .env file...\n";
        $envVars = [];
        $lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Skip Infisical configuration variables
                if (!in_array($key, ['INFISICAL_CLIENT_ID', 'INFISICAL_CLIENT_SECRET', 'INFISICAL_API_URL', 'INFISICAL_ENV'])) {
                    $envVars[] = [
                        'secretKey' => $key,
                        'secretValue' => $value,
                        'secretComment' => ''
                    ];
                }
            }
        }

        if (empty($envVars)) {
            echo "No secrets found in .env file to push.\n";
            return 0;
        }

        echo "✓ Found " . count($envVars) . " secrets to push\n";

        // Step 2: Login to get access token
        echo "2. Authenticating with Infisical...\n";
        $clientId = getenv('INFISICAL_CLIENT_ID');
        $clientSecret = getenv('INFISICAL_CLIENT_SECRET');
        $apiUrl = getenv('INFISICAL_API_URL');

        if (!$clientId || !$clientSecret || !$apiUrl) {
            fwrite(STDERR, "Error: Infisical configuration missing in .env\n");
            return 1;
        }

        $loginUrl = $apiUrl . '/api/v1/auth/universal-auth/login';
        $loginData = [
            'clientId' => $clientId,
            'clientSecret' => $clientSecret
        ];

        [$loginCode, $loginBody] = self::httpPost($loginUrl, $loginData, null);

        if ($loginCode < 200 || $loginCode >= 300) {
            echo "Authentication failed: HTTP $loginCode\n$loginBody\n";
            return 1;
        }

        $authData = json_decode($loginBody, true);
        if (!isset($authData['accessToken'])) {
            fwrite(STDERR, "Error: Invalid auth response.\n");
            return 1;
        }

        $token = $authData['accessToken'];
        echo "✓ Authentication successful\n";

        // Step 3: Push secrets
        echo "3. Pushing secrets to Infisical...\n";
        $projectConfig = self::getProjectConfig();
        if (!$projectConfig || !isset($projectConfig['projectId'])) {
            fwrite(STDERR, "Error: No project configuration found. Run 'mytool create-project' first.\n");
            return 1;
        }
        $projectId = $projectConfig['projectId'];
        $env = $projectConfig['environment'] ?: 'dev';

        $pushUrl = $apiUrl . '/api/v4/secrets/batch';
        $pushData = [
            'projectId' => $projectId,
            'environment' => $env,
            'secretPath' => '/',
            'secrets' => $envVars
        ];

        [$pushCode, $pushBody] = self::httpPost($pushUrl, $pushData, $token);

        if ($pushCode < 200 || $pushCode >= 300) {
            echo "Failed to push secrets: HTTP $pushCode\n$pushBody\n";
            return 1;
        }

        echo "✓ Successfully pushed " . count($envVars) . " secrets to Infisical\n";
        echo "Environment: $env\n";
        echo "Project ID: $projectId\n";
        echo "\nSecrets push completed successfully!\n";

        return 0;
    }
}