# GapGPT Provider Debugging Guide

## Status

❌ **Current:** GapGPT provider fails with SSL/connection error  
⚠️ **Impact:** Cannot use real AI model in current environment  
✅ **Workaround:** Demo provider (sample_image) works perfectly  

---

## Error Analysis

### Error Message

```
CURL error 56: OpenSSL SSL_read: OpenSSL/3.6.1: 
error:0A000126:SSL routines::unexpected eof while reading, errno 0
```

**What this means:**
- OpenSSL successfully initiated TLS connection
- Server sent data but closed connection unexpectedly
- Read failed partway through the response
- Common in: proxy interference, server timeout, rate limiting

---

## Diagnostic Steps

### Step 1: Test Endpoint Connectivity

```bash
# Basic connectivity test
curl -v https://api.gapgpt.app/v1/images/edits 2>&1 | head -20

# Expected: Connection established → TLS handshake → 400/401 error (no auth)
# Problem: Connection reset/closed during TLS handshake
```

### Step 2: Verify SSL Certificate

```bash
# Check certificate validity
openssl s_client -connect api.gapgpt.app:443 -showcerts 2>&1 | grep -A 5 "subject="

# Check certificate expiration
openssl s_client -connect api.gapgpt.app:443 2>&1 | grep "notAfter"

# Verify certificate chain
openssl s_client -connect api.gapgpt.app:443 -CApath /etc/ssl/certs 2>&1
```

### Step 3: Check API Availability

```bash
# Test with curl and proper headers
curl -v https://api.gapgpt.app/v1/images/edits \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -F "image=@/path/to/test.jpg" \
  -F "model=gpt-image-2" \
  -F "prompt=test" 2>&1

# Expected: 200 OK with image data
# Problem: Connection timeout/reset
```

### Step 4: Network Diagnostics

```bash
# Check routing to api.gapgpt.app
traceroute api.gapgpt.app

# DNS resolution
nslookup api.gapgpt.app
# Should resolve to IP address

# Check firewall (macOS)
sudo pfctl -s nat

# Check firewall (Linux)
sudo iptables -L -n
sudo ufw status
```

### Step 5: API Key Verification

```bash
# In .env file
cat .env | grep GAPGPT_API_KEY

# Expected format: sk-XXXXX (40+ characters)
# Problem: Invalid key, wrong key, missing key
```

---

## Code-Level Debugging

### Add Verbose Logging

Edit [GapGptImageEditProvider.php](../app/Services/AI/Providers/GapGptImageEditProvider.php):

```php
// Before HTTP request
Log::debug('GapGPT request details', [
    'endpoint' => $config['endpoint'],
    'model' => $config['model'],
    'method' => 'POST',
    'auth_header' => 'Bearer ' . substr($credentials['api_key'], 0, 10) . '...',
    'image_path' => $imagePath,
    'image_size' => filesize($imagePath),
]);

// After HTTP request
try {
    $response = Http::timeout(30)
        ->withHeaders([
            'Authorization' => 'Bearer ' . $credentials['api_key'],
        ])
        ->attach('image', fopen($imagePath, 'r'), 'image.jpg')
        ->post($endpoint)
        ->throw()
        ->json();
    
    Log::debug('GapGPT response received', [
        'status' => 200,
        'image_url' => $response['data'][0]['url'] ?? 'null',
        'size' => strlen($response),
    ]);
} catch (Exception $e) {
    Log::error('GapGPT request failed', [
        'exception' => get_class($e),
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile() . ':' . $e->getLine(),
    ]);
}
```

### Add Client Configuration

```php
// In provider, add custom HTTP client config
$response = Http::withOptions([
    'verify' => false,  // ⚠️ Only for testing! Disables SSL verification
    'timeout' => 90,
    'connect_timeout' => 30,
    'ssl_verify_peer' => false,
    'ssl_verify_host' => false,
])
->timeout(90)
->withHeaders([...])
->attach('image', ...)
->post($endpoint);
```

---

## Common Causes & Solutions

### Cause 1: API Key Invalid/Expired

**Symptoms:**
- 401 Unauthorized response
- Connection closes immediately

**Check:**
```bash
# Verify API key format in config
grep -A 5 "'gapgpt'" config/ai-models.php

# Test API key directly
curl -H "Authorization: Bearer $GAPGPT_API_KEY" \
  https://api.gapgpt.app/v1/models
```

**Solution:**
1. Verify API key in `config/ai-models.php` or `.env`
2. Check if key has been reset/regenerated
3. Contact GapGPT support if unsure

### Cause 2: Firewall/Proxy Interference

**Symptoms:**
- Intermittent SSL errors
- Works from one network, not another

**Check:**
```bash
# From production server, test connectivity
curl -v https://api.gapgpt.app/v1/images/edits

# Check proxy settings
env | grep -i proxy
```

**Solution:**
1. Disable corporate proxy (if applicable)
2. Add api.gapgpt.app to firewall allowlist
3. Check VPN isn't interfering
4. Whitelist GapGPT IP addresses (if available)

### Cause 3: SSL Certificate Issues

**Symptoms:**
- "certificate verify failed" errors
- Intermittent SSL errors

**Check:**
```bash
# Verify certificate chain
openssl s_client -connect api.gapgpt.app:443 -CApath /etc/ssl/certs

# Check system certificates
ls -la /etc/ssl/certs/ | grep -i "gapgpt\|root"
```

**Solution:**
1. Update system certificates: `sudo update-ca-certificates`
2. Temporarily disable verification (testing only):
   ```php
   Http::withoutVerifying()
   ```
3. Provide custom CA bundle:
   ```php
   Http::withOptions(['verify' => '/path/to/ca-bundle.crt'])
   ```

### Cause 4: Rate Limiting

**Symptoms:**
- Works initially, then fails after multiple requests
- 429 Too Many Requests errors

**Check:**
```bash
# Check response headers for rate limit info
curl -i https://api.gapgpt.app/v1/images/edits | head -20
```

**Solution:**
1. Add delay between requests:
   ```php
   sleep(1); // Wait 1 second
   ```
2. Implement exponential backoff:
   ```php
   $retries = 0;
   while ($retries < 3) {
       try {
           return $this->makeRequest();
       } catch (RateLimitException $e) {
           sleep(pow(2, $retries)); // 1s, 2s, 4s
           $retries++;
       }
   }
   ```

### Cause 5: Server Overload/Down

**Symptoms:**
- Connection timeout
- 503 Service Unavailable
- Intermittent failures

**Check:**
```bash
# Status page (if available)
curl https://status.gapgpt.app

# Contact support
# Check Twitter @gapgpt_status
```

**Solution:**
1. Wait for service to recover
2. Implement fallback provider
3. Use circuit breaker pattern
4. Add retry logic

---

## Temporary Workarounds

### Option 1: Disable SSL Verification (Development Only!)

⚠️ **SECURITY WARNING**: Only use for debugging in isolated dev environment!

```php
// app/Services/AI/Providers/GapGptImageEditProvider.php

protected function makeRequest($imagePath): string
{
    // ... existing code ...
    
    $response = Http::withoutVerifying()  // ⚠️ Disable SSL verification
        ->timeout(90)
        ->withHeaders([...])
        ->attach('image', fopen($imagePath, 'r'), 'image.jpg')
        ->post($endpoint)
        ->throw()
        ->json();
    
    // ...
}
```

### Option 2: Use Demo Provider as Fallback

```php
// In GenerationService.php

public function resolveProvider(...) 
{
    try {
        $provider = GapGptImageEditProvider::new();
        // Test connection quickly
        $provider->testConnection();
        return $provider;
    } catch (Exception $e) {
        Log::warning('GapGPT unavailable, falling back to demo', [
            'error' => $e->getMessage(),
        ]);
        return HairVisionDemoProvider::new();
    }
}
```

### Option 3: Implement Retry Logic

```php
// In ProcessGenerationJob.php

private function executeWithRetry($job, $maxRetries = 3)
{
    $attempts = 0;
    
    while ($attempts < $maxRetries) {
        try {
            return $this->executeProvider($job);
        } catch (ConnectionException $e) {
            $attempts++;
            if ($attempts >= $maxRetries) {
                throw $e;
            }
            sleep(pow(2, $attempts)); // Exponential backoff
            Log::info("Retry attempt $attempts for job {$job->uuid}");
        }
    }
}
```

---

## Testing GapGPT Integration

### Create Test Job

```bash
php artisan tinker --execute="
// Create test with GapGPT
\$upload = App\Models\Upload::first();
\$service = app(App\Services\GenerationService::class);
\$group = \$service->createForUpload(\$upload);
echo 'Test group created: ' . \$group->uuid . PHP_EOL;
"
```

### Run Single Job

```bash
php artisan queue:work --once --timeout=120
```

### Check Results

```bash
php artisan tinker --execute="
\$group = App\Models\GenerationGroup::latest()->first();
echo 'Group: ' . \$group->status . PHP_EOL;
\$group->jobs->each(function(\$job) {
    echo \$job->period . ': ' . \$job->status . PHP_EOL;
});
"
```

---

## Escalation

If GapGPT provider still fails after troubleshooting:

1. **Verify connectivity from production server** (not just dev)
2. **Check GapGPT status page** for service incidents
3. **Contact GapGPT support:**
   - Provide error logs
   - Include API key (last 4 chars only)
   - Include curl test results
4. **As fallback:** Use demo provider for production
5. **Plan migration:** Switch to different AI provider if GapGPT unreliable

---

## Next Steps

1. ✅ Run diagnostic steps above
2. ✅ Identify root cause
3. ✅ Apply solution
4. ✅ Test with single job: `php artisan queue:work --once`
5. ✅ Verify logs show success
6. ✅ Create full generation to confirm

**Current Recommendation:** Use demo provider for now, investigate GapGPT in production environment.
