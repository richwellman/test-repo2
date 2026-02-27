<?php
namespace Vanderbilt\REDCap\Classes\TwoFA\Duo;

use CurlHandle;
use Duo\DuoUniversal\DuoException;
use Duo\DuoUniversal\Client as DuoUniversalClient;

/**
 * Modified version of the Client library provided by DUO.
 * Uses the proxy configuration defined in the REDCap
 * Genaral Configuration settings page.
 */
class Client extends DuoUniversalClient {



    /**
     * Make HTTPS calls to Duo.
     *
     * @param string      $endpoint   The endpoint we are trying to hit
     * @param array       $request    Information to send to Duo
     * @param string|null $user_agent (Optional) A user-agent string
     *
     * @return array of strings
     * @throws DuoException For failure to connect to Duo
     */
    protected function makeHttpsCall(string $endpoint, array $request, ?string $user_agent = null): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://" . $this->api_host . $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_CAINFO, self::DUO_CERTS);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($user_agent !== null) {
            curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
        }
        if (!is_null($this->http_proxy)) {
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
            curl_setopt($ch, CURLOPT_PROXY, $this->http_proxy);
        };

        $this->applyProxyConfiguration($ch);

        $result = curl_exec($ch);

        /* Throw an error if the result doesn't exist or if our request returned a 5XX status */
        if (!$result) {
            throw new DuoException(self::FAILED_CONNECTION);
        }
        if (self::SUCCESS_STATUS_CODE !== curl_getinfo($ch, CURLINFO_HTTP_CODE)) {
            throw new DuoException($this->getExceptionFromResult(json_decode($result, true)));
        }
        return json_decode($result, true);
    }

    /**
     * apply the REDCap proxy configuration
     *
     * @param CurlHandle $ch
     * @return void
     */
    private function applyProxyConfiguration($ch) {
        curl_setopt($ch, CURLOPT_PROXY, PROXY_HOSTNAME); // If using a proxy
        curl_setopt($ch, CURLOPT_PROXYUSERPWD, PROXY_USERNAME_PASSWORD); // If using a proxy
    }

    /**
     * Retrieves exception message for DuoException from HTTPS result message.
     *
     * @param array $result The result from the HTTPS request
     *
     * @return string The exception message taken from the message or MALFORMED_RESPONSE
     */
    private function getExceptionFromResult(array $result): string
    {
        if (isset($result["message"]) && isset($result["message_detail"])) {
            return $result["message"] . ": " . $result["message_detail"];
        } elseif (isset($result["error"]) && isset($result["error_description"])) {
            return $result["error"] . ": " . $result["error_description"];
        }
        return self::MALFORMED_RESPONSE;
    }

}