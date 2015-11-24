<?php
namespace Http\Curl;

use Http\Client\Exception\RequestException;

/**
 * Simultaneous requests runner
 *
 * @license http://opensource.org/licenses/MIT MIT
 *
 * @author  Михаил Красильников <m.krasilnikov@yandex.ru>
 */
class MultiRunner
{
    /**
     * cURL multi handle
     *
     * @var resource|null
     */
    private $multiHandle = null;

    /**
     * cURL response parser
     *
     * @var ResponseParser
     */
    private $responseParser;

    /**
     * Awaiting cores
     *
     * @var PromiseCore[]
     */
    private $cores = [];

    /**
     * Construct new runner.
     *
     * @param ResponseParser $responseParser
     */
    public function __construct(ResponseParser $responseParser)
    {
        $this->responseParser = $responseParser;
    }

    /**
     * Release resources if still active
     */
    public function __destruct()
    {
        if (is_resource($this->multiHandle)) {
            curl_multi_close($this->multiHandle);
        }
    }

    /**
     * Add promise core
     *
     * @param PromiseCore $core
     */
    public function add(PromiseCore $core)
    {
        foreach ($this->cores as $existed) {
            if ($existed === $core) {
                return;
            }
        }

        $this->cores[] = $core;

        if (null === $this->multiHandle) {
            $this->multiHandle = curl_multi_init();
        }
        curl_multi_add_handle($this->multiHandle, $core->getHandle());
    }

    /**
     * Wait for request(s) to be completed.
     *
     * @param PromiseCore|null $targetCore
     */
    public function wait(PromiseCore $targetCore = null)
    {
        do {
            $status = curl_multi_exec($this->multiHandle, $active);
            $info = curl_multi_info_read($this->multiHandle);
            if (false !== $info) {
                $core = $this->findCoreByHandle($info['handle']);
                if (CURLE_OK === $info['result']) {
                    $response = $this->responseParser->parse(
                        curl_multi_getcontent($info['handle']),
                        curl_getinfo($info['handle'])
                    );
                    $core->fulfill($response);
                } else {
                    $error = curl_error($info['handle']);
                    $exception = new RequestException($error, $core->getRequest());
                    $core->reject($exception);
                }

                if ($core === $targetCore) {
                    return;
                }
            }
        } while ($status === CURLM_CALL_MULTI_PERFORM || $active);
    }

    /**
     * Find core by handle.
     *
     * @param resource $handle
     *
     * @return PromiseCore|null
     */
    private function findCoreByHandle($handle)
    {
        foreach ($this->cores as $core) {
            if ($core->getHandle() === $handle) {
                return $core;
            }
        }
        return null;
    }
}
