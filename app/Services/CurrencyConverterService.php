<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Models\ExchangeRate;
use Carbon\Carbon;

class CurrencyConverterService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://v6.exchangerate-api.com/v6/';

    public function __construct()
    {
        $this->apiKey = config('services.exchangerate_api.key');
    }

    /**
     * Converte um valor de uma moeda para outra.
     *
     * @param float $amount
     * @param string $fromCurrencyCode
     * @param string $toCurrencyCode
     * @param Carbon|null $date A data para a taxa de câmbio (null para a mais recente)
     * @return float|null Null se a conversão falhar.
     */
    public function convert(float $amount, string $fromCurrencyCode, string $toCurrencyCode, Carbon $date = null): ?float
    {
        if (strtoupper($fromCurrencyCode) === strtoupper($toCurrencyCode)) {
            return $amount;
        }

        $rate = $this->getExchangeRate($fromCurrencyCode, $toCurrencyCode, $date);

        if ($rate === null) {
            // Logar erro ou lançar exceção
            report("Falha ao obter taxa de câmbio de {$fromCurrencyCode} para {$toCurrencyCode}");
            return null;
        }

        return $amount * $rate;
    }

    /**
     * Obtém a taxa de câmbio.
     * Tenta primeiro da tabela local (se $date fornecida), depois do cache, depois da API.
     *
     * @param string $fromCurrencyCode
     * @param string $toCurrencyCode
     * @param Carbon|null $date
     * @return float|null
     */
    public function getExchangeRate(string $fromCurrencyCode, string $toCurrencyCode, Carbon $date = null): ?float
    {
        $fromCurrencyCode = strtoupper($fromCurrencyCode);
        $toCurrencyCode = strtoupper($toCurrencyCode);
        $cacheKey = "exchange_rate_{$fromCurrencyCode}_{$toCurrencyCode}_" . ($date ? $date->format('Y-m-d') : 'latest');
        $ttl = $date && $date->isPast() ? null : now()->addHours(12); // Cache indefinido para datas passadas, 12h para 'latest'


        // 1. Tentar da tabela local se a data for especificada e a tabela existir
        if ($date && class_exists(ExchangeRate::class)) {
            $dbRate = ExchangeRate::where('from_currency_code', $fromCurrencyCode)
                ->where('to_currency_code', $toCurrencyCode)
                ->where('date', $date->format('Y-m-d'))
                ->first();
            if ($dbRate) {
                return (float) $dbRate->rate;
            }
        }

        // 2. Tentar do Cache
        if (Cache::has($cacheKey)) {
            return (float) Cache::get($cacheKey);
        }

        // 3. Buscar da API
        $apiUrl = $this->baseUrl . $this->apiKey . ($date ? "/history/{$fromCurrencyCode}/{$date->format('Y-m-d')}" : "/latest/{$fromCurrencyCode}");

        try {
            $response = Http::timeout(10)->get($apiUrl); // Adicionado timeout

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['conversion_rates'][$toCurrencyCode])) {
                    $rate = (float) $data['conversion_rates'][$toCurrencyCode];

                    // Guardar no cache
                    if ($ttl) {
                        Cache::put($cacheKey, $rate, $ttl);
                    } else {
                        Cache::forever($cacheKey);
                    }


                    // Guardar na tabela local se data especificada e tabela existir
                    if ($date && class_exists(ExchangeRate::class) && !$dbRate) {
                        ExchangeRate::updateOrCreate(
                            [
                                'from_currency_code' => $fromCurrencyCode,
                                'to_currency_code' => $toCurrencyCode,
                                'date' => $date->format('Y-m-d')
                            ],
                            ['rate' => $rate]
                        );
                    }
                    return $rate;
                } elseif (isset($data['rates'][$toCurrencyCode])) { // Algumas APIs usam 'rates' em vez de 'conversion_rates' para histórico
                    $rate = (float) $data['rates'][$toCurrencyCode];
                    if ($ttl) Cache::put($cacheKey, $rate, $ttl); else Cache::forever($cacheKey);
                    if ($date && class_exists(ExchangeRate::class) && !$dbRate) { /* ...código para salvar no BD ... */ }
                    return $rate;
                }
            }
            report("API de Câmbio falhou ou taxa não encontrada: {$fromCurrencyCode} para {$toCurrencyCode}. Resposta: " . $response->body());
            return null;
        } catch (\Illuminate\Http\Client\RequestException $e) {
            report("Erro na requisição à API de Câmbio: " . $e->getMessage());
            return null;
        }
    }
}
