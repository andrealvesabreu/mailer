<?php
declare(strict_types = 1);
namespace Inspire\Mailer\Maildocker;

use Inspire\Support\Message\System\SystemMessage;

/**
 * Description of Client
 * Based in https://github.com/maildocker/maildocker-php
 *
 * @author aalves
 */
class Client
{

    const VERSION = 'v1';

    /**
     * API user
     *
     * @var string
     */
    private string $apiUser;

    /**
     * API key
     *
     * @var string
     */
    private string $apiKey;

    /**
     * Maildocker host
     *
     * @var string
     */
    private string $host;

    /**
     * Remote server port
     *
     * @var int
     */
    private int $port;

    /**
     * Server endpoint
     *
     * @var string
     */
    private string $endpoint;

    /**
     * Proxy comfiguration
     *
     * @var array
     */
    private array $proxy = [];

    /**
     * Full endpoint API address
     *
     * @var string
     */
    private string $mailUrl;

    /**
     * API version
     *
     * @var string
     */
    private string $version = self::VERSION;

    /**
     * Create a client to send mail to maildocker API
     *
     * @param string $apiKey
     * @param string $apiSecret
     * @param array $options
     */
    public function __construct(string $apiKey, string $apiSecret, ?array $options = null)
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->host = is_array($options) && isset($options['host']) ? $options['host'] : 'https://ecentry.io';
        $this->port = is_array($options) && isset($options['port']) ? $options['port'] : 443;
        $this->endpoint = is_array($options) && isset($options['endpoint']) ? $options['endpoint'] : "/api/maildocker/{$this->version}/mail/";
        $this->mailUrl = "{$this->host}:{$this->port}{$this->endpoint}";
        $this->proxy = is_array($options) && isset($options['proxy']) && is_array($options['proxy']) ? $options['proxy'] : [];
    }

    /**
     * Build a message
     *
     * @param Message $message
     * @return array
     */
    protected function build(Message $message): array
    {
        $values = [];
        foreach ((array) $message as $key => $value) {
            if ($value) {
                $values[$key] = $value;
            }
        }
        return $values;
    }

    /**
     * Send mail to API
     *
     * @param Message $message
     */
    public function send(Message $message): SystemMessage
    {
        $data = str_replace('\u0000*\u0000', '', json_encode($this->build($message)));
        $ch = curl_init($this->mailUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Basic " . base64_encode("{$this->apiKey}:{$this->apiSecret}")
        ]);
        if (isset($this->proxy['host']) && isset($this->proxy['port'])) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy['host']);
            curl_setopt($ch, CURLOPT_PROXYPORT, $this->proxy['port']);
        }
        curl_setopt_array($ch, [
            CURLOPT_TIMEOUT => 0,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data
        ]);
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        if ($response instanceof \stdClass && // In case of error
        property_exists($response, 'user_message')) {
            return new SystemMessage($response->user_message, // Message
            '0', // Status code
            SystemMessage::MSG_ERROR, // Message code
            false);
        } else {
            $resp = new SystemMessage('OK', // Message
            '1', // Status code
            SystemMessage::MSG_OK, // Message code
            true);
            $resp->addExtra((array) $response);
            return $resp;
        }
    }
}