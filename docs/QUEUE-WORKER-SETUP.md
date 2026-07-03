# Queue Worker Setup & Deployment Guide

## Local Development

### Start Queue Worker

```bash
cd /Users/satrap/Sites/hairvision-ai
php artisan queue:work --timeout=600 --tries=1
```

**Options Explained:**
- `--timeout=600`: Max 10 minutes per job (adjust based on your API response times)
- `--tries=1`: No automatic retries (set to 3+ for production)
- Runs in foreground (use `2>&1 &` or tmux to background)

### Verify Queue is Running

```bash
# Check if process exists
ps aux | grep "queue:work"

# Check Redis has jobs
redis-cli LLEN queues:default

# Monitor logs in real-time
tail -f storage/logs/laravel.log | grep -E "INFO|WARNING|ERROR"
```

### Test Generation

```bash
# Create a test generation (requires upload + lead)
php artisan tinker --execute="
dispatch(new App\Jobs\ProcessGenerationJob(
  App\Models\GenerationJob::first()
));
"
```

### Stop Queue Worker

```bash
# Find process ID
ps aux | grep "queue:work" | grep -v grep

# Kill it
kill -9 <PID>
```

---

## Production Deployment

### Option 1: Systemd Service (Recommended)

Create `/etc/systemd/system/laravel-queue.service`:

```ini
[Unit]
Description=Laravel Queue Worker
After=network.target redis.service mysql.service
Wants=redis.service mysql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/html/hairvision-ai
Environment="PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin"
Environment="APP_ENV=production"
ExecStart=/usr/bin/php artisan queue:work --timeout=600 --tries=3 --sleep=3
Restart=on-failure
RestartSec=5
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

Enable and start:

```bash
sudo systemctl daemon-reload
sudo systemctl enable laravel-queue
sudo systemctl start laravel-queue

# Monitor
sudo systemctl status laravel-queue
sudo journalctl -u laravel-queue -f
```

### Option 2: Supervisor

Install supervisor:

```bash
sudo apt-get install supervisor
```

Create `/etc/supervisor/conf.d/laravel-queue.conf`:

```ini
[program:laravel-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/hairvision-ai/artisan queue:work --timeout=600 --tries=3 --sleep=3
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/html/hairvision-ai/storage/logs/queue-worker.log
environment=APP_ENV=production
```

Enable:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-queue:*

# Monitor
sudo supervisorctl tail -f laravel-queue
```

### Option 3: Cron-Based (Not Recommended)

Add to crontab as `www-data` user:

```bash
* * * * * cd /var/www/html/hairvision-ai && php artisan queue:work --once --timeout=60
```

⚠️ **Issues:** High latency (up to 1 minute), high server load (every minute), use only as fallback.

---

## Configuration

### .env Settings

```bash
# Queue driver (must be redis for async)
QUEUE_CONNECTION=redis

# Redis connection
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Job timeout (seconds)
QUEUE_TIMEOUT=600

# Retry configuration
QUEUE_TRIES=3
QUEUE_RETRY_DELAY=30
```

### Queue Options in artisan command

| Option | Value | Purpose |
|--------|-------|---------|
| `--timeout` | 600 | Max seconds per job |
| `--tries` | 3 | Retry attempts |
| `--sleep` | 3 | Seconds between queue checks |
| `--max-jobs` | 1000 | Restart after N jobs |
| `--once` | - | Process 1 batch and exit |

---

## Monitoring & Health Checks

### Queue Depth

```bash
redis-cli LLEN queues:default
```

### Failed Jobs

```bash
# Check database
php artisan tinker --execute="
DB::table('generation_jobs')
  ->where('status', 'failed')
  ->orderBy('updated_at', 'desc')
  ->take(10)
  ->get()
  ->each(fn(\$j) => echo \$j->id . ': ' . \$j->last_error . PHP_EOL);
"
```

### Log Monitoring

```bash
# Real-time log view
tail -f storage/logs/laravel.log

# Count errors in last hour
tail -1000 storage/logs/laravel.log | grep -c ERROR

# Show recent failures
tail -200 storage/logs/laravel.log | grep -E "failed|error|exception"
```

### Health Check Script

```bash
#!/bin/bash
# save as check_queue.sh

QUEUE_COUNT=$(redis-cli LLEN queues:default)
PROCESS_COUNT=$(ps aux | grep -c "php artisan queue:work")

if [ $PROCESS_COUNT -lt 1 ]; then
    echo "ALERT: Queue worker not running!"
    exit 1
fi

if [ $QUEUE_COUNT -gt 100 ]; then
    echo "WARNING: Queue has $QUEUE_COUNT jobs pending"
    exit 1
fi

echo "OK: Queue worker running, $QUEUE_COUNT jobs pending"
exit 0
```

Run periodically:

```bash
*/5 * * * * /var/www/html/hairvision-ai/check_queue.sh | mail -s "Queue Status" ops@example.com
```

---

## Troubleshooting

### Queue Worker Not Processing Jobs

**Check:**
1. Is process running? `ps aux | grep queue:work`
2. Is Redis running? `redis-cli PING`
3. Are jobs in queue? `redis-cli LLEN queues:default`
4. Check logs: `tail -50 storage/logs/laravel.log`

**Fix:**
```bash
# Restart worker
killall php
php artisan queue:work --timeout=600 --tries=3 &

# Clear stuck jobs
redis-cli FLUSHDB  # ⚠️ Use with caution!
```

### Jobs Failing with Exceptions

```bash
# See error details
tail -200 storage/logs/laravel.log | grep -A 10 "local.ERROR\|local.CRITICAL"

# Check specific job
php artisan tinker --execute="
\$job = App\Models\GenerationJob::find(ID);
echo 'Status: ' . \$job->status . PHP_EOL;
echo 'Error: ' . \$job->last_error . PHP_EOL;
"
```

### High Memory Usage

```bash
# Check memory
php -r "echo ini_get('memory_limit');"

# Increase if needed in .env
PHP_MEMORY_LIMIT=512M

# Restart after N jobs (prevent memory leaks)
php artisan queue:work --max-jobs=1000
```

### Redis Connection Issues

```bash
# Test connection
redis-cli PING
# Should return: PONG

# Check Redis status
redis-cli INFO stats
```

---

## Performance Tuning

### Multiple Workers

For high load, run multiple queue workers:

```bash
# Option 1: Multiple processes
for i in {1..3}; do
    php artisan queue:work --timeout=600 --tries=3 &
done

# Option 2: Using supervisor (automatically manages)
# Set numprocs=3 in supervisor config
```

### Job Processing Priority

Jobs are processed in order (FIFO). To add priority:

```php
dispatch(new ProcessGenerationJob($job))->onQueue('high');
dispatch(new ProcessGenerationJob($job))->onQueue('default');
```

### Resource Limits

```bash
# Set limits in supervisor config
[program:laravel-queue]
# ... existing config ...
process_resource_limits_as_numbers=true
process_resource_rlimits=
    RLIMIT_NPROC=100:100
    RLIMIT_NOFILE=65536:65536
    RLIMIT_AS=unlimited:unlimited
```

---

## Backup & Recovery

### Backup Queue Jobs

```bash
# Export Redis data
redis-cli BGSAVE
# Creates dump.rdb

# Or use AOF (append-only file)
# Set appendonly yes in redis.conf
```

### Restore Queue

```bash
# Copy dump.rdb to Redis data directory
sudo cp dump.rdb /var/lib/redis/
sudo chown redis:redis /var/lib/redis/dump.rdb

# Restart Redis
sudo systemctl restart redis
```

---

## Production Checklist

- [ ] Queue worker configured in systemd or supervisor
- [ ] Redis running and accessible
- [ ] Log rotation configured
- [ ] Monitoring/alerting enabled
- [ ] Backup strategy for Redis data
- [ ] Retry logic configured (--tries=3+)
- [ ] Timeouts appropriate for API response times
- [ ] Health checks in place
- [ ] Runbooks for common issues
- [ ] Load testing completed

---

## Support

For issues, check:
1. Queue worker status: `systemctl status laravel-queue`
2. Redis status: `redis-cli PING`
3. Recent logs: `tail -100 storage/logs/laravel.log`
4. Database connectivity: `php artisan tinker` → `DB::connection()->getPdo()`
