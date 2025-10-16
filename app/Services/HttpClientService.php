<?php
// app/Services/HttpClientService.php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;

class HttpClientService
{
    /**
     * Mengirim request ke service external
     *
     * @param string $method GET, POST, PUT, DELETE
     * @param string $url URL endpoint
     * @param array $payload Data yang dikirim
     * @param array $headers Headers tambahan
     * @param int $timeout Timeout dalam detik
     * @param int $retries Jumlah retry
     * @param int $retryDelay Delay retry dalam milliseconds
     * @return array
     */
    public function sendRequest(
        string $method,
        string $url,
        array $payload = [],
        array $headers = [],
        int $timeout = 30,
        int $retries = 3,
        int $retryDelay = 100
    ): array {
        try {
            $defaultHeaders = [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ];

            $finalHeaders = array_merge($defaultHeaders, $headers);

            $response = Http::timeout($timeout)
                ->retry($retries, $retryDelay)
                ->withHeaders($finalHeaders);

            // Handle different methods
            switch (strtoupper($method)) {
                case 'GET':
                    $response = $response->get($url, $payload);
                    break;
                case 'POST':
                    $response = $response->post($url, $payload);
                    break;
                case 'PUT':
                    $response = $response->put($url, $payload);
                    break;
                case 'DELETE':
                    $response = $response->delete($url, $payload);
                    break;
                default:
                    $response = $response->post($url, $payload);
            }

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'data' => $response->json() ?? $response->body(),
                'headers' => $response->headers(),
                'body' => $response->body()
            ];
        } catch (ConnectionException $e) {
            return [
                'success' => false,
                'status' => 503,
                'error' => 'Connection timeout: ' . $e->getMessage(),
                'data' => null
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'status' => 500,
                'error' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Helper khusus untuk LIS Service
     *
     * @param string $endpoint Endpoint LIS (tanpa base URL)
     * @param array $payload Data payload
     * @param string $method HTTP method
     * @return array
     */
    public function sendToLIS(
        string $endpoint,
        array $payload,
        string $method = 'POST'
    ): array {
        $baseUrl = env('LIS_BASE_URL');
        $url = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');

        // var_dump("Sending to LIS: $method $url");

        return $this->sendRequest($method, $url, $payload);
    }

    /**
     * Helper khusus untuk order LIS
     *
     * @param array $orderPayload
     * @return array
     */
    public function sendOrderToLIS(array $orderPayload): array
    {
        return $this->sendToLIS('order', $orderPayload, 'POST');
    }
}
