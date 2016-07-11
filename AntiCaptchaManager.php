<?php
/**
 * Author: Ivan Lukyanov
 * Date: 01.04.2016
 */

namespace Bankon\IntegrationBundle\Util;

use Bankon\IntegrationBundle\Exception\AntiCaptchaException;
use GuzzleHttp\Client;

/**
 * Осуществляет обработку изображения с капчой через API сервиса http://anti-captcha.com
 */
class AntiCaptchaManager
{
    const CAPTCHA_NOT_READY = 'CAPCHA_NOT_READY';
    const CAPTCHA_TIMEOUT_HIT = 'CAPTCHA_TIMEOUT_HIT';

    /**
     * @var Client
     */
    private $client;

    /**
     * @var string
     */
    private $apikey;

    /**
     * @var string
     */
    private $host;

    /**
     * @var int
     */
    private $responseTimeout;

    /**
     * @var int
     */
    private $maxTimeout;

    /**
     * @var int
     */
    private $phrase;

    /**
     * @var int
     */
    private $caseSensitive;

    /**
     * @var int
     */
    private $numeric;

    /**
     * @var int
     */
    private $minLength;

    /**
     * @var int
     */
    private $maxLength;

    /**
     * @var int
     */
    private $russian;

    /**
     * @param string $apikey
     * @param string $host
     * @param int $responseTimeout
     * @param int $maxTimeout
     * @param int $phrase
     * @param int $caseSensitive
     * @param int $numeric
     * @param int $minLength
     * @param int $maxLength
     * @param int $russian
     */
    public function __construct(
        $apikey,
        $host = "http://antigate.com",
        $responseTimeout = 5,
        $maxTimeout = 120,
        $phrase = 0,
        $caseSensitive = 0,
        $numeric = 0,
        $minLength = 0,
        $maxLength = 0,
        $russian = 0
    )
    {
        $this->apikey = $apikey;
        $this->host = $host;
        $this->responseTimeout = $responseTimeout;
        $this->maxTimeout = $maxTimeout;
        $this->phrase = $phrase;
        $this->caseSensitive = $caseSensitive;
        $this->numeric = $numeric;
        $this->minLength = $minLength;
        $this->maxLength = $maxLength;
        $this->russian = $russian;

        $this->client = new Client(['base_uri' => $host]);
    }

    /**
     * Распознавание капчи
     *
     * @param $filename
     * @return string
     */
    public function recognize($filename)
    {
        try {
            $captchaId = $this->sendCaptcha($filename);
            $captchaText = $this->getCaptchaText($captchaId);
        } catch (AntiCaptchaException $e) {
            return $e->getMessage();
        }

        return $captchaText;
    }

    /**
     * Отправка файла с капчой в сервис
     *
     * @param $filename
     * @return string
     * @throws AntiCaptchaException
     */
    private function sendCaptcha($filename)
    {
        $response = $this->client->post('/in.php', [
            'multipart' => [
                ['name' => 'key', 'contents' => $this->apikey],
                ['name' => 'file', 'contents' => fopen($filename, 'r')]
            ]
        ]);
        $body = $response->getBody()->getContents();
        if (strpos($body, '|') === false) {
            throw new AntiCaptchaException($body);
        }

        $id = substr(strstr($body, '|'), 1);

        return $id;
    }

    /**
     * Получение результата с текстом капчи
     *
     * @param $captchaId
     * @return string
     * @throws AntiCaptchaException
     */
    private function getCaptchaText($captchaId)
    {
        for ($time = 0; $time <= $this->maxTimeout; $time += $this->responseTimeout) {
            $response = $this->client->get('/res.php', [
                'query' => [
                    'key' => $this->apikey,
                    'action' => 'get',
                    'id' => $captchaId,
                ]
            ]);
            $body = $response->getBody()->getContents();
            if (strpos($body, 'ERROR') !== false) {
                throw new AntiCaptchaException($body);
            }
            if ($body === self::CAPTCHA_NOT_READY) {
                sleep($this->responseTimeout);

                continue;
            } elseif (strpos($body, '|') !== false) {
                $text = substr(strstr($body, '|'), 1);

                return $text;
            }
        }

        throw new AntiCaptchaException(self::CAPTCHA_TIMEOUT_HIT);
    }
}
