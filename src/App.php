<?php
namespace Mojo\CliTool;

final class App
{
    public static function run(array $argv): int
    {
        self::loadEnv();
        array_shift($argv); // remove script name
        $cmd = $argv[0] ?? 'help';

        switch ($cmd) {
            case 'push':
                return self::pushSecrets($argv);

            case 'pull':
                return self::startupWorkflow($argv);

            case 'init':
                return self::initWorkflow($argv);

            case 'help':
            default:
                echo "Usage:\n";
                echo "  mojocli help\n";
                echo "  mojocli init --name=\"Project Name\" [--env=dev]\n";
                echo "  mojocli pull [--env=dev] [--output=.env.local]\n";
                echo "  mojocli push [--env=dev]\n";
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

    private static function httpPatch(string $url, array $data, ?string $token): array {
        $ch = curl_init($url);
        $headers = ['Content-Type: application/json'];
        if ($token) $headers[] = "Authorization: Bearer $token";
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PATCH',
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

    private static function loadEnv(): void {
        // Look for .env in current working directory (where user runs the command)
        // This is optional - credentials should be set as system environment variables
        $envFile = getcwd() . '/.env';
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
        $cwd = getcwd();

        // Try .infisical.json first (PHP format) in current working directory
        $phpConfigFile = $cwd . '/.infisical.json';
        if (file_exists($phpConfigFile)) {
            $config = json_decode(file_get_contents($phpConfigFile), true);
            if ($config) return $config;
        }

        // Fallback to infisical.json (bash format) in current working directory
        $bashConfigFile = $cwd . '/infisical.json';
        if (file_exists($bashConfigFile)) {
            $config = json_decode(file_get_contents($bashConfigFile), true);
            if ($config) {
                // Convert bash format to PHP format
                if (isset($config['workspaceId']) && !isset($config['projectId'])) {
                    $config['projectId'] = $config['workspaceId'];
                }
                return $config;
            }
        }

        return null;
    }

    private static function getApiUrl(): string {
        return 'https://secret.mojomosaic.com';
    }

    private static function normalizeEnvironment(string $env): string {
        $env = strtolower(trim($env));
        switch ($env) {
            case 'staging':
            case 'stage':
                return 'staging';
            case 'production':
            case 'prod':
                return 'prod';
            case 'development':
            case 'dev':
            default:
                return 'dev';
        }
    }

    private static function promptEnvironment(): string {
        echo "Select environment:\n";
        echo "1. Development (dev)\n";
        echo "2. Staging (staging)\n";
        echo "3. Production (prod)\n";
        echo "Enter choice [1-3]: ";

        $handle = fopen("php://stdin", "r");
        $choice = trim(fgets($handle));
        fclose($handle);

        switch ($choice) {
            case '2':
            case 'staging':
            case 'stage':
                return 'staging';
            case '3':
            case 'production':
            case 'prod':
                return 'prod';
            case '1':
            case 'development':
            case 'dev':
            default:
                return 'dev';
        }
    }

    private static function authenticate(?string $environment = null): ?string {
        $clientId = getenv('INFISICAL_CLIENT_ID');
        $clientSecret = getenv('INFISICAL_CLIENT_SECRET');

        if (!$environment) {
            $environment = self::promptEnvironment();
        }

        $apiUrl = self::getApiUrl();

        if (!$clientId || !$clientSecret) {
            fwrite(STDERR, "Error: INFISICAL_CLIENT_ID and INFISICAL_CLIENT_SECRET must be set as environment variables.\n");
            fwrite(STDERR, "Run: export INFISICAL_CLIENT_ID=\"your-client-id\"\n");
            fwrite(STDERR, "Run: export INFISICAL_CLIENT_SECRET=\"your-client-secret\"\n");
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



    private static function pushSecrets(array $argv): int {
        echo "Pushing secrets to Infisical...\n";

        // Step 1: Check if .env exists and parse it
        $envFile = getcwd() . '/.env';
        if (!file_exists($envFile)) {
            fwrite(STDERR, "Error: .env file not found in current directory.\n");
            return 1;
        }

        echo "1. Parsing .env file...\n";
        $envVars = [];
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

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
                if (!in_array($key, ['INFISICAL_CLIENT_ID', 'INFISICAL_CLIENT_SECRET', 'INFISICAL_ENV'])) {
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
        $envParam = self::opt($argv, 'env');
        $environment = $envParam ? self::normalizeEnvironment($envParam) : self::promptEnvironment();
        $token = self::authenticate($environment);
        if (!$token) {
            return 1;
        }

        $apiUrl = self::getApiUrl();
        echo "✓ Authentication successful\n";

        // Step 3: Push secrets individually (handles existing secrets)
        echo "3. Pushing secrets to Infisical...\n";
        $projectConfig = self::getProjectConfig();
        if (!$projectConfig || !isset($projectConfig['projectId'])) {
            fwrite(STDERR, "Error: No project configuration found. Run 'mojocli create-project' first.\n");
            return 1;
        }
        $projectId = $projectConfig['projectId'] ?? $projectConfig['workspaceId'] ?? null;
        $env = $environment; // Use the environment from CLI parameter or prompt

        if (!$projectId) {
            fwrite(STDERR, "Error: Invalid project configuration\n");
            return 1;
        }

        echo "📋 Project ID: $projectId\n";
        echo "🌍 Environment: $env\n";

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        foreach ($envVars as $secret) {
            $secretKey = $secret['secretKey'];
            echo "📝 Updating secret: $secretKey... ";

            // Try to update existing secret first
            $updateUrl = $apiUrl . '/api/v4/secrets/' . urlencode($secretKey);
            $updateData = [
                'projectId' => $projectId,
                'environment' => $env,
                'secretPath' => '/',
                'secretValue' => $secret['secretValue'],
                'secretComment' => $secret['secretComment'] ?? ''
            ];

            [$updateCode, $updateBody] = self::httpPatch($updateUrl, $updateData, $token);

            if ($updateCode >= 200 && $updateCode < 300) {
                echo "✅ Updated\n";
                $successCount++;
            } else {
                // If update fails, try to create new secret
                $createUrl = $apiUrl . '/api/v4/secrets/' . urlencode($secretKey);
                $createData = [
                    'projectId' => $projectId,
                    'environment' => $env,
                    'secretPath' => '/',
                    'secretKey' => $secretKey,
                    'secretValue' => $secret['secretValue'],
                    'secretComment' => $secret['secretComment'] ?? ''
                ];

                [$createCode, $createBody] = self::httpPost($createUrl, $createData, $token);

                if ($createCode >= 200 && $createCode < 300) {
                    echo "✅ Created\n";
                    $successCount++;
                } else {
                    echo "❌ Failed\n";
                    $errorCount++;
                    $errors[] = "$secretKey: HTTP $createCode - " . substr($createBody, 0, 100);
                }
            }
        }

        echo "\n📊 Results:\n";
        echo "✅ Successful: $successCount\n";
        if ($errorCount > 0) {
            echo "❌ Failed: $errorCount\n";
            echo "\nErrors:\n";
            foreach ($errors as $error) {
                echo "  • $error\n";
            }
        }
        echo "\nSecrets push completed!\n";

        return 0;
    }

    private static function initWorkflow(array $argv): int {
        echo "🚀 Initializing Infisical project (bash-compatible)...\n";

        // Get project name from command line
        $projectName = self::opt($argv, 'name');
        if (!$projectName) {
            fwrite(STDERR, "Error: Project name is required. Use --name=\"Project Name\"\n");
            return 1;
        }

        echo "📋 Project: $projectName\n";

        // Step 1: Authentication
        echo "🔑 Authenticating with Infisical...\n";
        $envParam = self::opt($argv, 'env');
        $environment = $envParam ? self::normalizeEnvironment($envParam) : self::promptEnvironment();
        $token = self::authenticate($environment);
        if (!$token) {
            return 1;
        }

        $apiUrl = self::getApiUrl();
        echo "🌐 API URL: $apiUrl\n";
        echo "✅ Authenticated successfully\n";

        // Step 2: Create project
        echo "🏗️ Creating project '$projectName'...\n";
        $slug = strtolower(str_replace([' ', '_'], '-', $projectName));

        // Make slug unique by adding timestamp
        $slug = $slug . '-' . time();

        $createUrl = $apiUrl . '/api/v1/projects';
        $createData = [
            'projectName' => $projectName,
            'projectDescription' => 'Demo project setup via script',
            'slug' => $slug,
            'template' => 'default',
            'type' => 'secret-manager',
            'shouldCreateDefaultEnvs' => true
        ];

        echo "🔍 Debug: Sending payload: " . json_encode($createData, JSON_PRETTY_PRINT) . "\n";
        [$createCode, $createBody] = self::httpPost($createUrl, $createData, $token);

        if ($createCode < 200 || $createCode >= 300) {
            echo "❌ Project creation failed: HTTP $createCode\n";
            echo "📋 URL: $createUrl\n";
            echo "📝 Response: $createBody\n";
            return 1;
        }

        $projectData = json_decode($createBody, true);

        // Extract project ID (handle different response formats)
        if (isset($projectData['project']['id'])) {
            $projectId = $projectData['project']['id'];
        } elseif (isset($projectData['project']['_id'])) {
            $projectId = $projectData['project']['_id'];
        } elseif (isset($projectData['id'])) {
            $projectId = $projectData['id'];
        } else {
            fwrite(STDERR, "❌ Invalid project creation response\n");
            return 1;
        }

        echo "✅ Project created successfully!\n";
        echo "📊 Project ID: $projectId\n";

        // Step 3: Save bash-compatible config
        echo "💾 Saving configuration to infisical.json...\n";
        $bashConfig = [
            'workspaceId' => $projectId,
            'apiUrl' => $apiUrl
        ];

        file_put_contents(getcwd() . '/infisical.json', json_encode($bashConfig, JSON_PRETTY_PRINT), LOCK_EX);
        echo "✅ Project initialized: $projectName ($projectId)\n";

        return 0;
    }

    private static function startupWorkflow(array $argv): int {
        echo "🚀 Starting up with Infisical secrets...\n";

        // Get options
        $envParam = self::opt($argv, 'env', 'dev');
        $env = self::normalizeEnvironment($envParam);
        $outputFile = self::opt($argv, 'output', '.env.local');

        echo "🌍 Environment: $env\n";
        echo "📝 Output file: $outputFile\n";

        // Step 1: Load project config
        $projectConfig = self::getProjectConfig();
        if (!$projectConfig) {
            fwrite(STDERR, "❌ No project configuration found. Run 'mojocli init' first.\n");
            return 1;
        }

        $projectId = $projectConfig['projectId'] ?? $projectConfig['workspaceId'] ?? null;

        if (!$projectId) {
            fwrite(STDERR, "❌ Invalid project configuration\n");
            return 1;
        }

        echo "📋 Project ID: $projectId\n";

        // Step 2: Authentication
        echo "🔑 Authenticating with Infisical...\n";
        $environment = $env; // Use the environment from command line parameter
        $token = self::authenticate($environment);
        if (!$token) {
            return 1;
        }

        $apiUrl = self::getApiUrl();
        echo "✅ Authenticated\n";

        // Step 3: Fetch secrets
        echo "⬇️ Fetching secrets for env: $env...\n";
        $secretsUrl = $apiUrl . "/api/v4/secrets?projectId=$projectId&environment=$env&secretPath=/&viewSecretValue=true";
        [$secretsCode, $secretsBody] = self::httpGet($secretsUrl, $token);

        if ($secretsCode < 200 || $secretsCode >= 300) {
            echo "❌ Failed to fetch secrets: HTTP $secretsCode\n$secretsBody\n";
            return 1;
        }

        $secretsData = json_decode($secretsBody, true);
        if (!isset($secretsData['secrets']) || !is_array($secretsData['secrets'])) {
            fwrite(STDERR, "❌ Invalid secrets format\n");
            return 1;
        }

        // Step 4: Write to output file
        echo "📝 Writing secrets to $outputFile...\n";
        $envContent = "";
        $secretCount = 0;

        foreach ($secretsData['secrets'] as $secret) {
            if (isset($secret['secretKey']) && isset($secret['secretValue'])) {
                $key = $secret['secretKey'];
                $value = $secret['secretValue'];
                $envContent .= "$key=$value\n";
                $secretCount++;
            }
        }

        file_put_contents(getcwd() . '/' . basename($outputFile), $envContent, LOCK_EX);

        // Step 5: Export to shell (display export commands)
        echo "✅ Secrets written to $outputFile and exported to current shell.\n";
        echo "💡 To export in your shell, run:\n";
        echo "   export \$(cat $outputFile | xargs)\n";

        echo "📊 Total secrets: $secretCount\n";

        return 0;
    }
}