<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ApiLog extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'api_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'service_name',
        'method',
        'endpoint',
        'payload',
        'response',
        'status_code',
        'status',
        'error_message',
        'ip_address',
        'user_agent',
        'request_id',
        'response_time'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'payload' => 'array',        // Sesuai dengan penggunaan Anda
        'response' => 'array',       // Untuk response nanti
        'response_time' => 'decimal:3', // Sesuai dengan perhitungan response time
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        // Sembunyikan field sensitif jika perlu
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESS = 'success';
    const STATUS_ERROR = 'error';

    /**
     * Scope untuk filter logs by status
     *
     * @param Builder $query
     * @param string $status
     * @return Builder
     */
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope untuk filter logs by service name
     *
     * @param Builder $query
     * @param string $serviceName
     * @return Builder
     */
    public function scopeService(Builder $query, string $serviceName): Builder
    {
        return $query->where('service_name', $serviceName);
    }

    /**
     * Scope untuk filter logs by date range
     *
     * @param Builder $query
     * @param string $startDate
     * @param string $endDate
     * @return Builder
     */
    public function scopeDateRange(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope untuk filter logs yang error
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeErrors(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ERROR)
            ->orWhere('status_code', '>=', 400);
    }

    /**
     * Scope untuk filter logs yang success
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeSuccess(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUCCESS)
            ->where('status_code', '<', 400);
    }

    /**
     * Scope untuk filter logs by HTTP method
     *
     * @param Builder $query
     * @param string $method
     * @return Builder
     */
    public function scopeMethod(Builder $query, string $method): Builder
    {
        return $query->where('method', strtoupper($method));
    }

    /**
     * Scope untuk filter logs by status code
     *
     * @param Builder $query
     * @param int $statusCode
     * @return Builder
     */
    public function scopeStatusCode(Builder $query, int $statusCode): Builder
    {
        return $query->where('status_code', $statusCode);
    }

    /**
     * Scope untuk filter logs by request ID
     *
     * @param Builder $query
     * @param string $requestId
     * @return Builder
     */
    public function scopeRequestId(Builder $query, string $requestId): Builder
    {
        return $query->where('request_id', $requestId);
    }

    /**
     * Scope untuk filter logs by IP address
     *
     * @param Builder $query
     * @param string $ipAddress
     * @return Builder
     */
    public function scopeIpAddress(Builder $query, string $ipAddress): Builder
    {
        return $query->where('ip_address', $ipAddress);
    }

    /**
     * Scope untuk mencari logs berdasarkan keyword
     *
     * @param Builder $query
     * @param string $keyword
     * @return Builder
     */
    public function scopeSearch(Builder $query, string $keyword): Builder
    {
        return $query->where(function ($q) use ($keyword) {
            $q->where('service_name', 'like', "%{$keyword}%")
                ->orWhere('endpoint', 'like', "%{$keyword}%")
                ->orWhere('error_message', 'like', "%{$keyword}%")
                ->orWhere('request_id', 'like', "%{$keyword}%");
        });
    }

    /**
     * Scope untuk logs dalam periode tertentu (hari ini, minggu ini, bulan ini)
     *
     * @param Builder $query
     * @param string $period
     * @return Builder
     */
    public function scopePeriod(Builder $query, string $period = 'today'): Builder
    {
        return match ($period) {
            'today' => $query->whereDate('created_at', today()),
            'week' => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'month' => $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]),
            'year' => $query->whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()]),
            default => $query->whereDate('created_at', today()),
        };
    }

    /**
     * Check jika log ini sukses
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS && $this->status_code < 400;
    }

    /**
     * Check jika log ini error
     *
     * @return bool
     */
    public function isError(): bool
    {
        return $this->status === self::STATUS_ERROR || $this->status_code >= 400;
    }

    /**
     * Check jika log ini pending
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Get response time in readable format
     *
     * @return string
     */
    public function getResponseTimeFormattedAttribute(): string
    {
        if ($this->response_time === null) {
            return 'N/A';
        }

        return $this->response_time . ' ms';
    }

    /**
     * Get short error message (truncated)
     *
     * @return string|null
     */
    public function getShortErrorMessageAttribute(): ?string
    {
        if (!$this->error_message) {
            return null;
        }

        return strlen($this->error_message) > 100
            ? substr($this->error_message, 0, 100) . '...'
            : $this->error_message;
    }

    /**
     * Get endpoint without query parameters
     *
     * @return string
     */
    public function getEndpointPathAttribute(): string
    {
        return parse_url($this->endpoint, PHP_URL_PATH) ?? $this->endpoint;
    }

    /**
     * Get request payload as pretty JSON
     *
     * @return string|null
     */
    public function getPayloadPrettyAttribute(): ?string
    {
        return $this->payload ? json_encode($this->payload, JSON_PRETTY_PRINT) : null;
    }

    /**
     * Get response as pretty JSON
     *
     * @return string|null
     */
    public function getResponsePrettyAttribute(): ?string
    {
        return $this->response ? json_encode($this->response, JSON_PRETTY_PRINT) : null;
    }

    /**
     * Get status badge class for UI
     *
     * @return string
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_SUCCESS => 'badge-success',
            self::STATUS_ERROR => 'badge-danger',
            self::STATUS_PENDING => 'badge-warning',
            default => 'badge-secondary',
        };
    }

    /**
     * Get status code badge class for UI
     *
     * @return string
     */
    public function getStatusCodeBadgeClassAttribute(): string
    {
        if ($this->status_code === null) {
            return 'badge-secondary';
        }

        return match (true) {
            $this->status_code >= 200 && $this->status_code < 300 => 'badge-success',
            $this->status_code >= 300 && $this->status_code < 400 => 'badge-info',
            $this->status_code >= 400 && $this->status_code < 500 => 'badge-warning',
            $this->status_code >= 500 => 'badge-danger',
            default => 'badge-secondary',
        };
    }

    /**
     * Clean up old logs (retention policy)
     *
     * @param int $days
     * @return int
     */
    public static function cleanupOldLogs(int $days = 30): int
    {
        $cutoffDate = now()->subDays($days);

        return self::where('created_at', '<', $cutoffDate)->delete();
    }

    /**
     * Get statistics for dashboard
     *
     * @return array
     */
    public static function getDashboardStats(): array
    {
        $today = today();

        return [
            'total_requests' => self::count(),
            'successful_requests' => self::success()->count(),
            'failed_requests' => self::errors()->count(),
            'today_requests' => self::whereDate('created_at', $today)->count(),
            'avg_response_time' => self::whereNotNull('response_time')->avg('response_time'),
            'top_services' => self::select('service_name')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('service_name')
                ->orderByDesc('count')
                ->limit(5)
                ->get()
                ->toArray(),
        ];
    }
}
