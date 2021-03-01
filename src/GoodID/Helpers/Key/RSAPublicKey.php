<?php
/**
 * Copyright 2017 ID&Trust, Ltd.
 *
 * You are hereby granted a non-exclusive, worldwide, royalty-free license to
 * use, copy, modify, and distribute this software in source code or binary form
 * for use in connection with the web services and APIs provided by ID&Trust.
 *
 * As with any software that integrates with the GoodID platform, your use
 * of this software is subject to the GoodID Terms of Service
 * (https://goodid.net/docs/tos).
 * This copyright notice shall be included in all copies or substantial portions
 * of the software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 *
 */

namespace GoodID\Helpers\Key;

use Base64Url\Base64Url;
use GoodID\Exception\GoodIDException;
use Jose\Factory\JWKFactory;
use Jose\KeyConverter\RSAKey as SpomkyRSAKey;
use Jose\Loader;
use Jose\Object\JWK;
use Jose\Object\JWKInterface;

/**
 * An RSA key class with sign/verify and encrypt/decrypt capabilities in the JWS/JWE format.
 * It can act as either a private or public key based on the parameters that it is initialized with.
 */
class RSAPublicKey implements KeyInterface
{
    /**
     * Signature algorithm parameter name
     */
    const SIG_ALG_KEY = "alg";

    /**
     * Signature algorithm:
     * RSASSA-PKCS1-v1_5 using SHA-256
     */
    const SIG_ALG_VALUE_RS256 = "RS256";

    /**
     * Key Identifier param.
     */
    const KEY_ID = "kid";

    /**
     * @var JWK
     */
    protected $jwk;

    /**
     * @var bool
     */
    protected $private;

    /**
     * RSAPublicKey constructor
     *
     * @param string|array $key PEM string or JWK array
     * @param array Additional key parameters.
     */
    public function __construct($key, array $values = array())
    {
        $spomkyRsaKey = new SpomkyRSAKey($key);
        $values = array_merge($spomkyRsaKey->toArray(), $values);

        if (!isset($values['kid']) || !isset($values['use'])) {
            throw new \Exception('Missing required key attributes: kid, use');
        }

        $values['alg'] = ($values['use'] == 'sig') ? self::SIG_ALG_VALUE_RS256 : 'RSA-OAEP';

        $this->jwk = new JWK($values);
        $this->private = $spomkyRsaKey->isPrivate();
    }

    /**
     * Verifies the signature and decodes the payload of the given compact JWS
     *
     * @param string $compactJws Compact JWS
     *
     * @return array The payload as an array
     *
     * @throws GoodIDException
     */
    public function verifyCompactJws($compactJws)
    {
        try {
            $loader = new Loader();

            return $loader->loadAndVerifySignatureUsingKey(
                $compactJws,
                $this->jwk,
                [self::SIG_ALG_VALUE_RS256]
            )->getPayload();
        } catch (\Exception $e) {
            throw new GoodIDException("Can not verify signature: " . $e->getMessage());
        }
    }

    /**
     * Get the public key as a JWK array
     *
     * @return array
     */
    public function getPublicKeyAsJwkArray()
    {
        return $this->jwk->toPublic()->JsonSerialize();
    }

    /**
     * @return string
     * @throws GoodIDException on error
     */
    public function getKid()
    {
        $values = $this->jwk->getAll();
        return $values['kid'];
    }

    /**
     * @param bool $includePrivate
     *
     * @return JWKInterface
     */
    public function asSpomkyKey($params = [], $includePrivate = false)
    {
        $jwk = $this->jwk;
        if (!$includePrivate) {
            $jwk = $jwk->toPublic();
        }

        return JWKFactory::createFromValues(array_merge($jwk->getAll(), $params));
    }

    /**
     * @return JWK
     */
    public function getJwk()
    {
        return $this->jwk;
    }

    /**
     * @return bool
     */
    public function isPrivate()
    {
        return $this->private;
    }
}
