<?php
declare(strict_types=1);

// Educational scanning helpers for enhanced subdomain and zero-rate modes.
// Safety: Use only on targets you own or have explicit permission to test.

function rh_parse_cli_args(array $argv): array
{
    $args = [];
    foreach ($argv as $i => $arg) {
        if ($i === 0) {
            continue;
        }
        if (strpos($arg, '--') !== 0) {
            continue;
        }
        $arg = substr($arg, 2);
        if ($arg === '') {
            continue;
        }
        if (strpos($arg, '=') !== false) {
            [$key, $value] = explode('=', $arg, 2);
            $args[$key] = $value;
        } else {
            $args[$arg] = true;
        }
    }

    if (isset($args['subdomain']) && !isset($args['mode'])) {
        $args['mode'] = 'subdomain';
    }
    if (isset($args['zero-rate']) && !isset($args['mode'])) {
        $args['mode'] = 'zero-rate';
    }

    return $args;
}

function rh_cli_entrypoint(array $argv): bool
{
    $args = rh_parse_cli_args($argv);
    if (isset($args['help']) || isset($args['h'])) {
        rh_cli_usage();
        return true;
    }
    if (!isset($args['mode'])) {
        return false;
    }

    $mode = strtolower((string) $args['mode']);
    if ($mode === 'subdomain' || $mode === 'subdomains') {
        rh_run_subdomain_scan($args, true);
        return true;
    }
    if ($mode === 'zero-rate' || $mode === 'zerorate' || $mode === 'zero_rate') {
        rh_run_zero_rate_scan($args, true);
        return true;
    }

    rh_cli_usage();
    return true;
}

function rh_cli_usage(): void
{
    echo "\nelitechwiz - Educational Enhanced Modes (CLI)\n";
    echo "Usage:\n";
    echo "  php elitechwiz.php --mode=subdomain --domain=example.com [options]\n";
    echo "  php elitechwiz.php --mode=zero-rate --hosts=hosts.txt [options]\n\n";
    echo "Common options:\n";
    echo "  --protocols=http,https   Protocols to scan (default: both)\n";
    echo "  --threads=50             Concurrent requests (default: 50)\n";
    echo "  --timeout=8              Request timeout seconds (default: 8)\n";
    echo "  --output=path            Output file path\n";
    echo "  --format=csv|json|txt     Output format (default: csv)\n";
    echo "  --resume=path            Resume using previous results file\n";
    echo "  --fail-log=path           Log failed requests to file\n\n";
    echo "Subdomain scan options:\n";
    echo "  --wordlist=path           Subdomain wordlist file\n";
    echo "  --permutations=1|0        Add common subdomain variations (default: 1)\n";
    echo "  --live-codes=200,301,...  Override live HTTP status codes\n\n";
    echo "Zero-rate scan options:\n";
    echo "  --hosts=path              Hostnames list (one per line)\n";
    echo "  --domain=example.com      Root domain (used with --wordlist)\n";
    echo "  --domains=path            Root domain list file (used with --wordlist)\n";
    echo "  --method=GET|HEAD|POST    HTTP method (default: HEAD)\n";
    echo "  --post-data=string        POST body (when method=POST)\n";
    echo "  --dns=1|0                 DNS pre-check (default: 1)\n";
    echo "  --fingerprints=default    Enable built-in header/body patterns\n";
    echo "  --header-patterns=a,b     Custom header patterns (comma separated)\n";
    echo "  --body-patterns=a,b       Custom body patterns (comma separated)\n";
    echo "  --body-pattern-mode=skip|upgrade   If method=HEAD, skip or upgrade to GET\n\n";
    echo "Safety: This tool is for educational and authorized testing only.\n\n";
}
function rh_prompt_line(string $message, ?string $default = null): string
{
    if (function_exists('userinput')) {
        $prompt = $default !== null ? $message . " [" . $default . "]" : $message;
        userinput($prompt);
    } else {
        $prompt = $default !== null ? $message . " [" . $default . "]" : $message;
        echo $prompt . ": ";
    }

    $input = trim((string) fgets(STDIN, 1024));
    if ($input === '' && $default !== null) {
        return $default;
    }

    return $input;
}

function rh_prompt_yes_no(string $message, bool $defaultYes = false): bool
{
    $suffix = $defaultYes ? ' [Y/n]' : ' [y/N]';
    $input = rh_prompt_line($message . $suffix);
    if ($input === '') {
        return $defaultYes;
    }
    return rh_to_bool($input, $defaultYes);
}

function rh_to_bool($value, bool $default = false): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if ($value === null) {
        return $default;
    }
    $value = strtolower((string) $value);
    if (in_array($value, ['1', 'true', 'yes', 'y', 'on'], true)) {
        return true;
    }
    if (in_array($value, ['0', 'false', 'no', 'n', 'off'], true)) {
        return false;
    }
    return $default;
}

function rh_parse_list($value): array
{
    if (is_array($value)) {
        return $value;
    }
    if ($value === null || $value === '') {
        return [];
    }
    $parts = array_map('trim', explode(',', (string) $value));
    $parts = array_filter($parts, static fn($item) => $item !== '');
    return array_values(array_unique($parts));
}

function rh_parse_protocols($value, array $default = ['http', 'https']): array
{
    if ($value === null || $value === '') {
        return $default;
    }
    if (is_array($value)) {
        $list = $value;
    } else {
        $value = strtolower((string) $value);
        if ($value === 'both') {
            $list = ['http', 'https'];
        } else {
            $list = rh_parse_list($value);
        }
    }
    $out = [];
    foreach ($list as $proto) {
        $proto = strtolower(trim($proto));
        if ($proto === 'http' || $proto === 'https') {
            $out[] = $proto;
        }
    }
    return $out ?: $default;
}

function rh_normalize_domain(string $input): string
{
    $domain = trim($input);
    $domain = preg_replace('#^https?://#i', '', $domain);
    $domain = preg_replace('#/.*$#', '', $domain);
    $domain = trim($domain);
    $domain = rtrim($domain, '.');
    return strtolower($domain);
}

function rh_is_valid_domain(string $domain): bool
{
    if ($domain === '' || strpos($domain, '.') === false) {
        return false;
    }
    return filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
}

function rh_read_lines(string $path): array
{
    if (!is_file($path)) {
        return [];
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return [];
    }
    return $lines;
}

function rh_read_wordlist(string $path): array
{
    $lines = rh_read_lines($path);
    $words = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
            continue;
        }
        $line = strtolower($line);
        $line = trim($line);
        $line = trim($line, '.');
        if ($line === '') {
            continue;
        }
        $words[] = $line;
    }
    $words = array_values(array_unique($words));
    return $words;
}

function rh_default_subdomain_words(): array
{
    return [
        'www', 'www1', 'www2', 'admin', 'administrator', 'api', 'apis', 'app', 'apps',
        'assets', 'beta', 'blog', 'cdn', 'chat', 'cms', 'cpanel', 'db', 'demo', 'dev',
        'devops', 'docs', 'download', 'downloads', 'edge', 'files', 'forum', 'ftp',
        'git', 'gitlab', 'github', 'help', 'images', 'img', 'imap', 'internal',
        'intranet', 'jenkins', 'jira', 'lab', 'mail', 'mail2', 'media', 'mobile',
        'monitor', 'mx', 'ns1', 'ns2', 'ns3', 'ns4', 'portal', 'prod', 'qa', 'sso',
        'smtp', 'stage', 'staging', 'static', 'status', 'store', 'support', 'test',
        'test1', 'test2', 'uat', 'vpn', 'web', 'webmail', 'wiki'
    ];
}

function rh_build_subdomain_candidates(string $domain, array $words): array
{
    $hosts = [];
    foreach ($words as $word) {
        $word = trim((string) $word);
        if ($word === '' || $word === '@' || $word === '*') {
            continue;
        }
        $word = trim($word, '.');
        if ($word === '') {
            continue;
        }
        if (str_contains($word, '.')) {
            if (str_ends_with($word, $domain)) {
                $host = $word;
            } else {
                $host = $word . '.' . $domain;
            }
        } else {
            $host = $word . '.' . $domain;
        }
        $hosts[$host] = true;
    }

    return array_keys($hosts);
}

function rh_resolve_host(string $host): array
{
    $ips = [];
    $records = @dns_get_record($host, DNS_A + DNS_AAAA);
    if (is_array($records)) {
        foreach ($records as $record) {
            if (!empty($record['ip'])) {
                $ips[] = $record['ip'];
            }
            if (!empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }
    }

    if (empty($ips)) {
        $ip = gethostbyname($host);
        if ($ip && $ip !== $host) {
            $ips[] = $ip;
        }
    }

    $ips = array_values(array_unique($ips));
    return $ips;
}

function rh_build_targets(array $hosts, array $protocols, array $ipMap = []): array
{
    $targets = [];
    foreach ($hosts as $host) {
        foreach ($protocols as $proto) {
            $proto = strtolower($proto);
            $port = $proto === 'https' ? 443 : 80;
            $targets[] = [
                'host' => $host,
                'protocol' => $proto,
                'port' => $port,
                'url' => $proto . '://' . $host . '/',
                'ip' => $ipMap[$host] ?? ''
            ];
        }
    }
    return $targets;
}
class RhResultWriter
{
    private $handle;
    private string $format;
    private array $headers;
    private bool $first = true;

    public function __construct(string $path, string $format, array $headers, bool $append = false)
    {
        $this->format = strtolower($format);
        $this->headers = $headers;
        $mode = $append ? 'ab' : 'wb';
        $this->handle = fopen($path, $mode);
        if (!$this->handle) {
            throw new RuntimeException('Could not open output file: ' . $path);
        }

        if (!$append) {
            if ($this->format === 'csv') {
                fputcsv($this->handle, $this->headers);
            } elseif ($this->format === 'txt') {
                fwrite($this->handle, '# ' . implode("\t", $this->headers) . PHP_EOL);
            } elseif ($this->format === 'json') {
                fwrite($this->handle, '[');
            }
        } else {
            if ($this->format === 'json') {
                // JSON append is not supported; caller should avoid append for JSON.
                fwrite($this->handle, '[');
            }
        }
    }

    public function write(array $row): void
    {
        if ($this->format === 'csv') {
            $ordered = [];
            foreach ($this->headers as $header) {
                $ordered[] = $row[$header] ?? '';
            }
            fputcsv($this->handle, $ordered);
            return;
        }

        if ($this->format === 'txt') {
            $ordered = [];
            foreach ($this->headers as $header) {
                $ordered[] = $row[$header] ?? '';
            }
            fwrite($this->handle, implode("\t", $ordered) . PHP_EOL);
            return;
        }

        if ($this->format === 'json') {
            $json = json_encode($row, JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                $json = '{}';
            }
            if (!$this->first) {
                fwrite($this->handle, ',');
            }
            fwrite($this->handle, $json);
            $this->first = false;
            return;
        }
    }

    public function close(): void
    {
        if ($this->format === 'json') {
            fwrite($this->handle, ']');
        }
        fclose($this->handle);
    }
}

class RhFailLogger
{
    private $handle;
    private bool $wroteHeader = false;

    public function __construct(string $path)
    {
        $isNew = !file_exists($path);
        $this->handle = fopen($path, 'ab');
        if (!$this->handle) {
            throw new RuntimeException('Could not open fail log: ' . $path);
        }
        if ($isNew) {
            fputcsv($this->handle, ['timestamp', 'host', 'protocol', 'port', 'error', 'status_code']);
            $this->wroteHeader = true;
        }
    }

    public function log(array $row): void
    {
        $data = [
            date('c'),
            $row['host'] ?? '',
            $row['protocol'] ?? '',
            $row['port'] ?? '',
            $row['error'] ?? '',
            $row['status_code'] ?? ''
        ];
        fputcsv($this->handle, $data);
    }

    public function close(): void
    {
        fclose($this->handle);
    }
}

function rh_parse_headers(string $rawHeaders): array
{
    $headers = [];
    $lines = preg_split("/\r\n|\n|\r/", trim($rawHeaders));
    if (!is_array($lines)) {
        return $headers;
    }
    foreach ($lines as $line) {
        if (strpos($line, ':') === false) {
            continue;
        }
        [$name, $value] = explode(':', $line, 2);
        $name = strtolower(trim($name));
        $value = trim($value);
        if ($name === '') {
            continue;
        }
        if (isset($headers[$name])) {
            if (is_array($headers[$name])) {
                $headers[$name][] = $value;
            } else {
                $headers[$name] = [$headers[$name], $value];
            }
        } else {
            $headers[$name] = $value;
        }
    }
    return $headers;
}

function rh_extract_title(string $body): string
{
    if ($body === '') {
        return '';
    }
    $body = substr($body, 0, 20000);
    if (preg_match('/<title[^>]*>(.*?)<\/title>/ims', $body, $matches)) {
        $title = trim($matches[1]);
        $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5);
        return $title;
    }
    return '';
}

function rh_match_patterns(string $haystack, array $patterns): array
{
    $matches = [];
    foreach ($patterns as $pattern) {
        $pattern = trim((string) $pattern);
        if ($pattern === '') {
            continue;
        }
        if (stripos($haystack, $pattern) !== false) {
            $matches[] = $pattern;
        }
    }
    return $matches;
}

function rh_is_live_status(int $status, array $liveCodes): bool
{
    return in_array($status, $liveCodes, true);
}

function rh_print_progress(int $processed, int $total, int $live, int $failed): void
{
    $msg = sprintf("\r[Progress] %d/%d | Live: %d | Failed: %d", $processed, $total, $live, $failed);
    echo $msg;
    if ($processed >= $total) {
        echo PHP_EOL;
    }
    flush();
}
function rh_probe_targets(array $targets, array $options, callable $onResult, callable $onFail, ?callable $onProgress = null): void
{
    $concurrency = max(1, (int) ($options['concurrency'] ?? 50));
    $method = strtoupper((string) ($options['method'] ?? 'GET'));
    $timeout = max(1, (int) ($options['timeout'] ?? 8));
    $connectTimeout = max(1, (int) ($options['connect_timeout'] ?? 5));
    $userAgent = (string) ($options['user_agent'] ?? 'elitechwiz/edu');
    $captureBody = (bool) ($options['capture_body'] ?? false);
    $maxBodySize = max(1024, (int) ($options['max_body_size'] ?? 20000));
    $useRange = (bool) ($options['use_range'] ?? false);
    $headerPatterns = $options['header_patterns'] ?? [];
    $bodyPatterns = $options['body_patterns'] ?? [];
    $bodyPatternMode = strtolower((string) ($options['body_pattern_mode'] ?? 'skip'));
    $liveCodes = $options['live_codes'] ?? [];
    $countLiveMode = (string) ($options['count_live_mode'] ?? 'responsive');

    if ($method === 'HEAD' && !empty($bodyPatterns) && $bodyPatternMode === 'upgrade') {
        $method = 'GET';
        $captureBody = true;
    }

    $mh = curl_multi_init();
    $queue = $targets;
    $active = [];
    $total = count($targets);
    $processed = 0;
    $live = 0;
    $failed = 0;

    while (!empty($queue) || !empty($active)) {
        while (count($active) < $concurrency && !empty($queue)) {
            $target = array_shift($queue);
            $ch = curl_init();
            $url = $target['url'];

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_ENCODING, '');

            if ($method === 'HEAD') {
                curl_setopt($ch, CURLOPT_NOBODY, true);
            } elseif ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                if (!empty($options['post_data'])) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $options['post_data']);
                }
            }

            if ($useRange && $captureBody && $method === 'GET') {
                curl_setopt($ch, CURLOPT_RANGE, '0-' . ($maxBodySize - 1));
            }

            $active[(int) $ch] = ['handle' => $ch, 'target' => $target];
            curl_multi_add_handle($mh, $ch);
        }

        do {
            $mrc = curl_multi_exec($mh, $running);
        } while ($mrc === CURLM_CALL_MULTI_PERFORM);

        if ($running) {
            curl_multi_select($mh, 0.1);
        }

        while ($info = curl_multi_info_read($mh)) {
            $ch = $info['handle'];
            $key = (int) $ch;
            $target = $active[$key]['target'];

            $response = curl_multi_getcontent($ch);
            $curlErrNo = curl_errno($ch);
            $curlErr = $curlErrNo ? curl_error($ch) : '';
            $infoData = curl_getinfo($ch);

            $httpCode = (int) ($infoData['http_code'] ?? 0);
            $totalTime = (float) ($infoData['total_time'] ?? 0.0);
            $primaryIp = (string) ($infoData['primary_ip'] ?? '');
            $headerSize = (int) ($infoData['header_size'] ?? 0);
            $rawHeader = $headerSize > 0 ? substr($response, 0, $headerSize) : '';
            $body = $headerSize > 0 ? substr($response, $headerSize) : $response;
            $headerBlocks = preg_split("/\r\n\r\n|\n\n|\r\r/", trim($rawHeader));
            $lastHeader = '';
            if (is_array($headerBlocks) && !empty($headerBlocks)) {
                $lastHeader = $headerBlocks[count($headerBlocks) - 1];
            } else {
                $lastHeader = $rawHeader;
            }
            $headers = rh_parse_headers($lastHeader);

            $server = '';
            if (isset($headers['server'])) {
                $server = is_array($headers['server']) ? $headers['server'][0] : $headers['server'];
            }

            $redirectUrl = '';
            if (isset($headers['location'])) {
                $redirectUrl = is_array($headers['location']) ? $headers['location'][0] : $headers['location'];
            }

            $contentLength = 0;
            if (isset($headers['content-length'])) {
                $contentLength = (int) (is_array($headers['content-length']) ? $headers['content-length'][0] : $headers['content-length']);
            } else {
                $contentLength = (int) ($infoData['size_download'] ?? 0);
            }

            $title = '';
            $contentType = '';
            if (isset($headers['content-type'])) {
                $contentType = is_array($headers['content-type']) ? $headers['content-type'][0] : $headers['content-type'];
            }
            if ($captureBody && $body !== '' && stripos($contentType, 'text/html') !== false) {
                $title = rh_extract_title($body);
            }

            $notes = [];
            if (!empty($headerPatterns)) {
                $headerText = '';
                foreach ($headers as $hName => $hValue) {
                    if (is_array($hValue)) {
                        $hValue = implode('; ', $hValue);
                    }
                    $headerText .= $hName . ': ' . $hValue . "\n";
                }
                $matches = rh_match_patterns($headerText, $headerPatterns);
                if (!empty($matches)) {
                    $notes[] = 'header_matches=' . implode('|', $matches);
                }
            }
            if (!empty($bodyPatterns) && $body !== '') {
                $matches = rh_match_patterns($body, $bodyPatterns);
                if (!empty($matches)) {
                    $notes[] = 'body_matches=' . implode('|', $matches);
                }
            }
            if ($redirectUrl !== '' && stripos($redirectUrl, 'captive') !== false) {
                $notes[] = 'redirect_hint=captive_portal';
            }

            $result = [
                'host' => $target['host'],
                'protocol' => $target['protocol'],
                'port' => $target['port'],
                'ip' => $target['ip'] ?: $primaryIp,
                'status_code' => $httpCode,
                'response_time_ms' => (int) round($totalTime * 1000),
                'content_length' => $contentLength,
                'server' => $server,
                'title' => $title,
                'redirect_url' => $redirectUrl,
                'notes' => implode(';', $notes)
            ];

            if ($curlErrNo !== 0 || $httpCode === 0) {
                $failed++;
                $onFail([
                    'host' => $target['host'],
                    'protocol' => $target['protocol'],
                    'port' => $target['port'],
                    'error' => $curlErrNo !== 0 ? $curlErr : 'NO_RESPONSE',
                    'status_code' => $httpCode
                ]);
            } else {
                $isLive = !empty($liveCodes) ? rh_is_live_status($httpCode, $liveCodes) : ($httpCode >= 200 && $httpCode < 400);
                $result['is_live'] = $isLive;
                $onResult($result);
                if ($countLiveMode === 'is_live') {
                    if ($isLive) {
                        $live++;
                    }
                } else {
                    $live++;
                }
            }

            $processed++;
            if ($onProgress) {
                $onProgress($processed, $total, $live, $failed);
            }

            unset($active[$key]);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
    }

    curl_multi_close($mh);
}
function rh_default_output_path(string $prefix, string $name, string $format): string
{
    $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $name);
    $time = date('Ymd_His');
    return 'results' . DIRECTORY_SEPARATOR . $prefix . '_' . $safe . '_' . $time . '.' . $format;
}

function rh_ensure_directory(string $path): void
{
    $dir = dirname($path);
    if ($dir !== '' && $dir !== '.' && !is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

function rh_prepare_output_path(string $path, string $format, bool $append): array
{
    $format = strtolower($format);
    if ($path === '') {
        return ['', false];
    }
    if ($format === 'json' && $append && file_exists($path)) {
        $newPath = preg_replace('/\.json$/i', '', $path) . '_new.json';
        return [$newPath, false];
    }
    return [$path, $append];
}

function rh_load_resume_keys(string $path, string $format, array $keyFields): array
{
    if ($path === '' || !is_file($path)) {
        return [];
    }
    $format = strtolower($format);
    $keys = [];

    if ($format === 'csv') {
        $handle = fopen($path, 'rb');
        if (!$handle) {
            return [];
        }
        $header = fgetcsv($handle);
        if (!is_array($header)) {
            fclose($handle);
            return [];
        }
        $index = [];
        foreach ($header as $i => $name) {
            $index[$name] = $i;
        }
        while (($row = fgetcsv($handle)) !== false) {
            $data = [];
            foreach ($keyFields as $field) {
                $pos = $index[$field] ?? null;
                $data[$field] = $pos !== null && isset($row[$pos]) ? $row[$pos] : '';
            }
            $keys[rh_make_key($data, $keyFields)] = true;
        }
        fclose($handle);
    } elseif ($format === 'json') {
        $data = json_decode((string) file_get_contents($path), true);
        if (is_array($data)) {
            foreach ($data as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $keys[rh_make_key($row, $keyFields)] = true;
            }
        }
    } elseif ($format === 'txt') {
        $lines = rh_read_lines($path);
        foreach ($lines as $line) {
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = explode("\t", $line);
            $data = [];
            foreach ($keyFields as $i => $field) {
                $data[$field] = $parts[$i] ?? '';
            }
            $keys[rh_make_key($data, $keyFields)] = true;
        }
    }

    return $keys;
}

function rh_make_key(array $row, array $keyFields): string
{
    $parts = [];
    foreach ($keyFields as $field) {
        $value = $row[$field] ?? '';
        $parts[] = strtolower((string) $value);
    }
    return implode('|', $parts);
}

function rh_print_safety_warning(string $modeLabel): void
{
    echo "\n[!] SAFETY WARNING: $modeLabel is for educational and authorized testing only.\n";
    echo "[!] Do not scan targets without explicit permission.\n";
    echo "[!] High-speed scanning may violate terms of service or laws.\n\n";
}

function rh_default_subdomain_config(): array
{
    return [
        'protocols' => ['http', 'https'],
        'threads' => 50,
        'timeout' => 8,
        'wordlist' => 'wordlists' . DIRECTORY_SEPARATOR . 'elitechwiz_subdomains_default.txt',
        'permutations' => true,
        'format' => 'csv',
        'output' => '',
        'resume' => '',
        'fail_log' => '',
        'live_codes' => [200, 204, 301, 302, 303, 307, 308, 401, 403],
        'show_progress' => true,
        'show_each' => false,
        'pause_after' => true,
        'interactive' => true,
        'count_live_mode' => 'is_live',
        'show_warning' => true
    ];
}

function rh_default_zero_rate_config(): array
{
    return [
        'protocols' => ['http', 'https'],
        'threads' => 100,
        'timeout' => 8,
        'method' => 'HEAD',
        'dns' => true,
        'format' => 'csv',
        'output' => '',
        'resume' => '',
        'fail_log' => '',
        'header_patterns' => [],
        'body_patterns' => [],
        'body_pattern_mode' => 'skip',
        'show_progress' => true,
        'show_each' => false,
        'pause_after' => true,
        'interactive' => true,
        'count_live_mode' => 'responsive',
        'show_warning' => true
    ];
}
function rh_run_subdomain_scan(array $options = [], bool $cli = false): void
{
    $config = rh_default_subdomain_config();
    $config['interactive'] = !$cli;

    if (isset($options['domain'])) {
        $config['domain'] = (string) $options['domain'];
    }

    if (isset($options['protocols'])) {
        $config['protocols'] = rh_parse_protocols($options['protocols'], $config['protocols']);
    }

    if (isset($options['threads'])) {
        $config['threads'] = max(1, (int) $options['threads']);
    }

    if (isset($options['timeout'])) {
        $config['timeout'] = max(1, (int) $options['timeout']);
    }

    if (isset($options['wordlist'])) {
        $config['wordlist'] = (string) $options['wordlist'];
    }

    if (isset($options['permutations'])) {
        $config['permutations'] = rh_to_bool($options['permutations'], true);
    }

    if (isset($options['format'])) {
        $config['format'] = strtolower((string) $options['format']);
    }

    if (isset($options['output'])) {
        $config['output'] = (string) $options['output'];
    }

    if (isset($options['resume'])) {
        $config['resume'] = (string) $options['resume'];
    }

    if (isset($options['fail-log'])) {
        $config['fail_log'] = (string) $options['fail-log'];
    }

    if (isset($options['live-codes'])) {
        $codes = rh_parse_list($options['live-codes']);
        $parsed = [];
        foreach ($codes as $code) {
            $parsed[] = (int) $code;
        }
        if (!empty($parsed)) {
            $config['live_codes'] = $parsed;
        }
    }

    if (isset($options['show-progress'])) {
        $config['show_progress'] = rh_to_bool($options['show-progress'], true);
    }

    if (isset($options['show-each'])) {
        $config['show_each'] = rh_to_bool($options['show-each'], false);
    }

    if (isset($options['pause-after'])) {
        $config['pause_after'] = rh_to_bool($options['pause-after'], true);
    }

    if (isset($options['show-warning'])) {
        $config['show_warning'] = rh_to_bool($options['show-warning'], true);
    }

    if (isset($options['interactive'])) {
        $config['interactive'] = rh_to_bool($options['interactive'], !$cli);
    }

    if (empty($config['domain']) && $config['interactive']) {
        $config['domain'] = rh_prompt_line('Enter root domain (e.g., example.com)');
    }

    $domain = rh_normalize_domain((string) ($config['domain'] ?? ''));
    if (!rh_is_valid_domain($domain)) {
        echo "\n[!] Invalid domain.\n";
        return;
    }

    if ($config['show_warning']) {
        rh_print_safety_warning('Subdomain Scan Mode');
    }

    if ($config['interactive'] && empty($options['protocols'])) {
        $choice = rh_prompt_line('Protocols: 1) HTTP 2) HTTPS 3) BOTH', '3');
        if ($choice === '1') {
            $config['protocols'] = ['http'];
        } elseif ($choice === '2') {
            $config['protocols'] = ['https'];
        } else {
            $config['protocols'] = ['http', 'https'];
        }
    }

    if ($config['interactive'] && empty($options['wordlist'])) {
        $useDefault = rh_prompt_yes_no('Use default subdomain wordlist?', true);
        if (!$useDefault) {
            $path = rh_prompt_line('Enter custom wordlist path');
            if ($path !== '') {
                $config['wordlist'] = $path;
            }
        }
    }

    if ($config['interactive'] && !isset($options['permutations'])) {
        $config['permutations'] = rh_prompt_yes_no('Add common subdomain permutations?', true);
    }

    if ($config['interactive'] && !isset($options['threads'])) {
        $threads = rh_prompt_line('Threads (10, 25, 50, 100, 200 or custom)', (string) $config['threads']);
        $config['threads'] = max(1, (int) $threads);
    }

    if ($config['interactive'] && empty($options['format'])) {
        $format = rh_prompt_line('Output format (csv/json/txt)', $config['format']);
        $config['format'] = strtolower($format);
    }

    if ($config['interactive'] && empty($options['output'])) {
        $config['output'] = rh_default_output_path('subdomains', $domain, $config['format']);
        $custom = rh_prompt_line('Output file path', $config['output']);
        $config['output'] = $custom !== '' ? $custom : $config['output'];
    } elseif ($config['output'] === '') {
        $config['output'] = rh_default_output_path('subdomains', $domain, $config['format']);
    }

    if ($config['interactive'] && empty($options['resume'])) {
        $resume = rh_prompt_yes_no('Resume from previous results file?', false);
        if ($resume) {
            $config['resume'] = rh_prompt_line('Resume file path');
        }
    }

    if ($config['interactive'] && empty($options['fail-log'])) {
        $failLog = rh_prompt_yes_no('Log failed requests to a file?', false);
        if ($failLog) {
            $defaultFail = rh_default_output_path('subdomains_failures', $domain, 'csv');
            $config['fail_log'] = rh_prompt_line('Fail log path', $defaultFail);
        }
    }

    if (!empty($config['fail_log'])) {
        $config['fail_log'] = (string) $config['fail_log'];
    }

    $words = rh_read_wordlist($config['wordlist']);
    if (empty($words)) {
        echo "\n[!] Wordlist empty or not found: " . $config['wordlist'] . "\n";
        return;
    }

    if ($config['permutations']) {
        $words = array_merge($words, rh_default_subdomain_words());
        $words = array_values(array_unique($words));
    }

    $hosts = rh_build_subdomain_candidates($domain, $words);
    if (empty($hosts)) {
        echo "\n[!] No subdomains generated.\n";
        return;
    }

    if ($config['interactive']) {
        echo "\n[i] Generated " . count($hosts) . " subdomain candidates. Resolving DNS...\n";
    }

    $ipMap = [];
    $failLogger = null;
    if (!empty($config['fail_log'])) {
        try {
            rh_ensure_directory($config['fail_log']);
            $failLogger = new RhFailLogger($config['fail_log']);
        } catch (RuntimeException $e) {
            echo "\n[!] " . $e->getMessage() . "\n";
        }
    }

    $resolved = 0;
    $totalHosts = count($hosts);
    foreach ($hosts as $i => $host) {
        $ips = rh_resolve_host($host);
        if (!empty($ips)) {
            $ipMap[$host] = implode(';', $ips);
            $resolved++;
        } else {
            if ($failLogger) {
                $failLogger->log([
                    'host' => $host,
                    'protocol' => 'dns',
                    'port' => '',
                    'error' => 'DNS_NO_RECORD',
                    'status_code' => ''
                ]);
            }
        }
        if ($config['show_progress'] && ($i % 25 === 0 || $i + 1 === $totalHosts)) {
            rh_print_progress($i + 1, $totalHosts, $resolved, $i + 1 - $resolved);
        }
    }

    if (empty($ipMap)) {
        echo "\n[!] No subdomains resolved.\n";
        if ($failLogger) {
            $failLogger->close();
        }
        return;
    }

    $targets = rh_build_targets(array_keys($ipMap), $config['protocols'], $ipMap);

    $resumeKeys = [];
    if (!empty($config['resume'])) {
        $resumeKeys = rh_load_resume_keys($config['resume'], $config['format'], ['host', 'protocol', 'port']);
        if (!empty($resumeKeys)) {
            $targets = array_values(array_filter($targets, static function ($target) use ($resumeKeys) {
                $key = strtolower($target['host']) . '|' . strtolower($target['protocol']) . '|' . $target['port'];
                return !isset($resumeKeys[$key]);
            }));
        }
    }

    if (empty($targets)) {
        echo "\n[!] Nothing left to scan (resume file already contains these targets).\n";
        if ($failLogger) {
            $failLogger->close();
        }
        return;
    }
    $headers = ['host', 'ip', 'protocol', 'port', 'status_code', 'response_time_ms', 'server', 'title', 'redirect_url'];
    [$outputPath, $append] = rh_prepare_output_path($config['output'], $config['format'], false);
    rh_ensure_directory($outputPath);
    try {
        $writer = new RhResultWriter($outputPath, $config['format'], $headers, $append);
    } catch (RuntimeException $e) {
        echo "\n[!] " . $e->getMessage() . "\n";
        if ($failLogger) {
            $failLogger->close();
        }
        return;
    }

    if ($config['interactive']) {
        echo "\n[i] Probing " . count($targets) . " targets with " . $config['threads'] . " threads...\n";
    }

    $onResult = function (array $result) use ($writer, $config): void {
        if (!empty($config['live_codes']) && empty($result['is_live'])) {
            return;
        }
        $writer->write([
            'host' => $result['host'],
            'ip' => $result['ip'],
            'protocol' => $result['protocol'],
            'port' => $result['port'],
            'status_code' => $result['status_code'],
            'response_time_ms' => $result['response_time_ms'],
            'server' => $result['server'],
            'title' => $result['title'],
            'redirect_url' => $result['redirect_url']
        ]);
        if ($config['show_each']) {
            echo "[+] {$result['host']} ({$result['protocol']}) {$result['status_code']} {$result['server']}\n";
        }
    };

    $onFail = function (array $fail) use ($failLogger): void {
        if ($failLogger) {
            $failLogger->log($fail);
        }
    };

    $onProgress = $config['show_progress'] ? 'rh_print_progress' : null;

    rh_probe_targets($targets, [
        'concurrency' => $config['threads'],
        'method' => 'GET',
        'timeout' => $config['timeout'],
        'connect_timeout' => 5,
        'user_agent' => 'elitechwiz/edu',
        'capture_body' => true,
        'max_body_size' => 20000,
        'header_patterns' => [],
        'body_patterns' => [],
        'body_pattern_mode' => 'skip',
        'live_codes' => $config['live_codes'],
        'count_live_mode' => $config['count_live_mode']
    ], $onResult, $onFail, $onProgress);

    $writer->close();
    if ($failLogger) {
        $failLogger->close();
    }

    echo "\n[i] Subdomain scan complete. Results: " . $outputPath . "\n";

    if ($config['pause_after']) {
        echo "\n[*] Press Enter to continue...\n";
        trim((string) fgets(STDIN, 1024));
    }
}
function rh_run_zero_rate_scan(array $options = [], bool $cli = false): void
{
    $config = rh_default_zero_rate_config();
    $config['interactive'] = !$cli;

    if (isset($options['protocols'])) {
        $config['protocols'] = rh_parse_protocols($options['protocols'], $config['protocols']);
    }

    if (isset($options['threads'])) {
        $config['threads'] = max(1, (int) $options['threads']);
    }

    if (isset($options['timeout'])) {
        $config['timeout'] = max(1, (int) $options['timeout']);
    }

    if (isset($options['method'])) {
        $config['method'] = strtoupper((string) $options['method']);
    }

    if (isset($options['post-data'])) {
        $config['post_data'] = (string) $options['post-data'];
    }

    if (isset($options['dns'])) {
        $config['dns'] = rh_to_bool($options['dns'], true);
    }

    if (isset($options['format'])) {
        $config['format'] = strtolower((string) $options['format']);
    }

    if (isset($options['output'])) {
        $config['output'] = (string) $options['output'];
    }

    if (isset($options['resume'])) {
        $config['resume'] = (string) $options['resume'];
    }

    if (isset($options['fail-log'])) {
        $config['fail_log'] = (string) $options['fail-log'];
    }

    if (isset($options['fingerprints'])) {
        if ((string) $options['fingerprints'] === 'default') {
            $config['header_patterns'] = ['x-zero-rated', 'x-freebasics', 'x-captive-portal', 'x-portal'];
            $config['body_patterns'] = ['free basics', 'zero rated', 'captive portal', 'walled garden'];
        }
    }

    if (isset($options['header-patterns'])) {
        $config['header_patterns'] = rh_parse_list($options['header-patterns']);
    }

    if (isset($options['body-patterns'])) {
        $config['body_patterns'] = rh_parse_list($options['body-patterns']);
    }

    if (isset($options['body-pattern-mode'])) {
        $config['body_pattern_mode'] = strtolower((string) $options['body-pattern-mode']);
    }

    if (isset($options['show-progress'])) {
        $config['show_progress'] = rh_to_bool($options['show-progress'], true);
    }

    if (isset($options['show-each'])) {
        $config['show_each'] = rh_to_bool($options['show-each'], false);
    }

    if (isset($options['pause-after'])) {
        $config['pause_after'] = rh_to_bool($options['pause-after'], true);
    }

    if (isset($options['show-warning'])) {
        $config['show_warning'] = rh_to_bool($options['show-warning'], true);
    }

    $hosts = [];

    if ($config['show_warning']) {
        rh_print_safety_warning('Zero Rate Host Scan Mode');
    }

    if ($config['interactive']) {
        echo "[i] Choose input type:\n";
        echo "    1) Host list file (FQDN per line)\n";
        echo "    2) Root domain(s) + wordlist\n";
        $choice = rh_prompt_line('Select option', '1');

        if ($choice === '1') {
            $path = rh_prompt_line('Host list file path');
            $hosts = rh_read_wordlist($path);
        } else {
            $domainsInput = rh_prompt_line('Root domain(s) (comma separated or file path)');
            $domains = [];
            if (is_file($domainsInput)) {
                $domains = rh_read_wordlist($domainsInput);
            } else {
                $domains = rh_parse_list($domainsInput);
            }
            $domains = array_map('rh_normalize_domain', $domains);
            $domains = array_filter($domains, 'rh_is_valid_domain');

            $wordlist = rh_prompt_line('Wordlist path', 'wordlists' . DIRECTORY_SEPARATOR . 'elitechwiz_subdomains_default.txt');
            $words = rh_read_wordlist($wordlist);
            foreach ($domains as $domain) {
                $hosts = array_merge($hosts, rh_build_subdomain_candidates($domain, $words));
            }
        }

        if (!isset($options['dns'])) {
            $config['dns'] = rh_prompt_yes_no('DNS pre-check (recommended)', true);
        }

        if (!isset($options['protocols'])) {
            $choice = rh_prompt_line('Protocols: 1) HTTP 2) HTTPS 3) BOTH', '3');
            if ($choice === '1') {
                $config['protocols'] = ['http'];
            } elseif ($choice === '2') {
                $config['protocols'] = ['https'];
            } else {
                $config['protocols'] = ['http', 'https'];
            }
        }

        if (!isset($options['method'])) {
            $method = rh_prompt_line('Method (GET/HEAD/POST)', $config['method']);
            $config['method'] = strtoupper($method);
        }

        if ($config['method'] === 'POST' && !isset($options['post-data'])) {
            $config['post_data'] = rh_prompt_line('POST body (can be empty)', '');
        }

        if (!isset($options['threads'])) {
            $threads = rh_prompt_line('Threads (10, 25, 50, 100, 200 or custom)', (string) $config['threads']);
            $config['threads'] = max(1, (int) $threads);
        }

        if (empty($options['fingerprints']) && empty($options['header-patterns']) && empty($options['body-patterns'])) {
            $enableFp = rh_prompt_yes_no('Enable zero-rate fingerprint checks?', false);
            if ($enableFp) {
                $config['header_patterns'] = ['x-zero-rated', 'x-freebasics', 'x-captive-portal', 'x-portal'];
                $config['body_patterns'] = ['free basics', 'zero rated', 'captive portal', 'walled garden'];
            }
        }

        if ($config['method'] === 'HEAD' && (!empty($config['body_patterns']) || !empty($options['body-patterns']))) {
            $upgrade = rh_prompt_yes_no('Body checks need GET. Upgrade to GET?', false);
            $config['body_pattern_mode'] = $upgrade ? 'upgrade' : 'skip';
        }

        if (!isset($options['format'])) {
            $format = rh_prompt_line('Output format (csv/json/txt)', $config['format']);
            $config['format'] = strtolower($format);
        }

        if (empty($options['output'])) {
            $config['output'] = rh_default_output_path('zero_rate', 'hosts', $config['format']);
            $custom = rh_prompt_line('Output file path', $config['output']);
            $config['output'] = $custom !== '' ? $custom : $config['output'];
        }

        if (empty($options['resume'])) {
            $resume = rh_prompt_yes_no('Resume from previous results file?', false);
            if ($resume) {
                $config['resume'] = rh_prompt_line('Resume file path');
            }
        }

        if (empty($options['fail-log'])) {
            $failLog = rh_prompt_yes_no('Log failed requests to a file?', true);
            if ($failLog) {
                $defaultFail = rh_default_output_path('zero_rate_failures', 'hosts', 'csv');
                $config['fail_log'] = rh_prompt_line('Fail log path', $defaultFail);
            }
        }
    } else {        if (isset($options['hosts'])) {
            $hosts = rh_read_wordlist((string) $options['hosts']);
        } elseif (isset($options['domain']) || isset($options['domains'])) {
            $domains = [];
            if (isset($options['domains']) && is_file((string) $options['domains'])) {
                $domains = rh_read_wordlist((string) $options['domains']);
            } elseif (isset($options['domain'])) {
                $domains = rh_parse_list((string) $options['domain']);
            }
            $domains = array_map('rh_normalize_domain', $domains);
            $domains = array_filter($domains, 'rh_is_valid_domain');

            $wordlist = (string) ($options['wordlist'] ?? ('wordlists' . DIRECTORY_SEPARATOR . 'elitechwiz_subdomains_default.txt'));
            $words = rh_read_wordlist($wordlist);
            foreach ($domains as $domain) {
                $hosts = array_merge($hosts, rh_build_subdomain_candidates($domain, $words));
            }
        }
    }

    $hosts = array_values(array_unique($hosts));
    if (empty($hosts)) {
        echo "\n[!] No hosts to scan. Provide --hosts or --domain/--domains with --wordlist.\n";
        return;
    }

    if ($config['output'] === '') {
        $config['output'] = rh_default_output_path('zero_rate', 'hosts', $config['format']);
    }

    $resumeKeys = [];
    if (!empty($config['resume'])) {
        $resumeKeys = rh_load_resume_keys($config['resume'], $config['format'], ['host', 'protocol', 'port']);
    }

    $ipMap = [];
    if ($config['dns']) {
        if ($config['interactive']) {
            echo "\n[i] DNS pre-check enabled. Resolving hosts...\n";
        }
        $resolved = 0;
        $totalHosts = count($hosts);
        foreach ($hosts as $i => $host) {
            $ips = rh_resolve_host($host);
            if (!empty($ips)) {
                $ipMap[$host] = implode(';', $ips);
                $resolved++;
            }
            if ($config['show_progress'] && ($i % 25 === 0 || $i + 1 === $totalHosts)) {
                rh_print_progress($i + 1, $totalHosts, $resolved, $i + 1 - $resolved);
            }
        }
        $hosts = array_keys($ipMap);
    }

    if (empty($hosts)) {
        echo "\n[!] No hosts resolved.\n";
        return;
    }

    $targets = rh_build_targets($hosts, $config['protocols'], $ipMap);
    if (!empty($resumeKeys)) {
        $targets = array_values(array_filter($targets, static function ($target) use ($resumeKeys) {
            $key = strtolower($target['host']) . '|' . strtolower($target['protocol']) . '|' . $target['port'];
            return !isset($resumeKeys[$key]);
        }));
    }

    if (empty($targets)) {
        echo "\n[!] Nothing left to scan (resume file already contains these targets).\n";
        return;
    }

    $headers = ['host', 'protocol', 'port', 'status_code', 'response_time_ms', 'content_length', 'server', 'title', 'redirect_url', 'notes'];
    [$outputPath, $append] = rh_prepare_output_path($config['output'], $config['format'], false);
    rh_ensure_directory($outputPath);
    try {
        $writer = new RhResultWriter($outputPath, $config['format'], $headers, $append);
    } catch (RuntimeException $e) {
        echo "\n[!] " . $e->getMessage() . "\n";
        return;
    }

    $failLogger = null;
    if (!empty($config['fail_log'])) {
        try {
            rh_ensure_directory($config['fail_log']);
            $failLogger = new RhFailLogger($config['fail_log']);
        } catch (RuntimeException $e) {
            echo "\n[!] " . $e->getMessage() . "\n";
        }
    }

    if ($config['interactive']) {
        echo "\n[i] Probing " . count($targets) . " targets with " . $config['threads'] . " threads...\n";
    }
    $onResult = function (array $result) use ($writer, $config): void {
        $writer->write([
            'host' => $result['host'],
            'protocol' => $result['protocol'],
            'port' => $result['port'],
            'status_code' => $result['status_code'],
            'response_time_ms' => $result['response_time_ms'],
            'content_length' => $result['content_length'],
            'server' => $result['server'],
            'title' => $result['title'],
            'redirect_url' => $result['redirect_url'],
            'notes' => $result['notes']
        ]);
        if ($config['show_each']) {
            echo "[+] {$result['host']} ({$result['protocol']}) {$result['status_code']} {$result['response_time_ms']}ms\n";
        }
    };

    $onFail = function (array $fail) use ($failLogger): void {
        if ($failLogger) {
            $failLogger->log($fail);
        }
    };

    $onProgress = $config['show_progress'] ? 'rh_print_progress' : null;

    rh_probe_targets($targets, [
        'concurrency' => $config['threads'],
        'method' => $config['method'],
        'timeout' => $config['timeout'],
        'connect_timeout' => 5,
        'user_agent' => 'elitechwiz/edu',
        'capture_body' => !empty($config['body_patterns']) || $config['method'] === 'GET',
        'max_body_size' => 20000,
        'header_patterns' => $config['header_patterns'],
        'body_patterns' => $config['body_patterns'],
        'body_pattern_mode' => $config['body_pattern_mode'],
        'live_codes' => [],
        'count_live_mode' => $config['count_live_mode'],
        'post_data' => $config['post_data'] ?? ''
    ], $onResult, $onFail, $onProgress);

    $writer->close();
    if ($failLogger) {
        $failLogger->close();
    }

    echo "\n[i] Zero-rate scan complete. Results: " . $outputPath . "\n";

    if ($config['pause_after']) {
        echo "\n[*] Press Enter to continue...\n";
        trim((string) fgets(STDIN, 1024));
    }
}
