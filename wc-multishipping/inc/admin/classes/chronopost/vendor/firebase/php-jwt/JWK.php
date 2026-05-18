<?php

namespace Firebase\JWT;

use DomainException;
use InvalidArgumentException;
use UnexpectedValueException;

class JWK
{
    private const OID = '1.2.840.10045.2.1';
    private const ASN1_OBJECT_IDENTIFIER = 0x06;
    private const ASN1_SEQUENCE = 0x10; // also defined in JWT
    private const ASN1_BIT_STRING = 0x03;
    private const EC_CURVES = [
        'P-256' => '1.2.840.10045.3.1.7', // Len: 64
        'secp256k1' => '1.3.132.0.10', // Len: 64
        'P-384' => '1.3.132.0.34', // Len: 96
    ];

    private const OKP_SUBTYPES = [
        'Ed25519' => true, // RFC 8037
    ];

    public static function parseKeySet(array $jwks, string $defaultAlg = null): array
    {
        $keys = [];

        if (!isset($jwks['keys'])) {
            throw new UnexpectedValueException('"keys" member must exist in the JWK Set');
        }

        if (empty($jwks['keys'])) {
            throw new InvalidArgumentException('JWK Set did not contain any keys');
        }

        foreach ($jwks['keys'] as $k => $v) {
            $kid = isset($v['kid']) ? $v['kid'] : $k;
            if ($key = self::parseKey($v, $defaultAlg)) {
                $keys[(string) $kid] = $key;
            }
        }

        if (0 === \count($keys)) {
            throw new UnexpectedValueException('No supported algorithms found in JWK Set');
        }

        return $keys;
    }

    public static function parseKey(array $jwk, string $defaultAlg = null): ?Key
    {
        if (empty($jwk)) {
            throw new InvalidArgumentException('JWK must not be empty');
        }

        if (!isset($jwk['kty'])) {
            throw new UnexpectedValueException('JWK must contain a "kty" parameter');
        }

        if (!isset($jwk['alg'])) {
            if (\is_null($defaultAlg)) {
                throw new UnexpectedValueException('JWK must contain an "alg" parameter');
            }
            $jwk['alg'] = $defaultAlg;
        }

        switch ($jwk['kty']) {
            case 'RSA':
                if (!empty($jwk['d'])) {
                    throw new UnexpectedValueException('RSA private keys are not supported');
                }
                if (!isset($jwk['n']) || !isset($jwk['e'])) {
                    throw new UnexpectedValueException('RSA keys must contain values for both "n" and "e"');
                }

                $pem = self::createPemFromModulusAndExponent($jwk['n'], $jwk['e']);
                $publicKey = \openssl_pkey_get_public($pem);
                if (false === $publicKey) {
                    throw new DomainException(
                        'OpenSSL error: ' . \openssl_error_string()
                    );
                }
                return new Key($publicKey, $jwk['alg']);
            case 'EC':
                if (isset($jwk['d'])) {
                    throw new UnexpectedValueException('Key data must be for a public key');
                }

                if (empty($jwk['crv'])) {
                    throw new UnexpectedValueException('crv not set');
                }

                if (!isset(self::EC_CURVES[$jwk['crv']])) {
                    throw new DomainException('Unrecognised or unsupported EC curve');
                }

                if (empty($jwk['x']) || empty($jwk['y'])) {
                    throw new UnexpectedValueException('x and y not set');
                }

                $publicKey = self::createPemFromCrvAndXYCoordinates($jwk['crv'], $jwk['x'], $jwk['y']);
                return new Key($publicKey, $jwk['alg']);
            case 'OKP':
                if (isset($jwk['d'])) {
                    throw new UnexpectedValueException('Key data must be for a public key');
                }

                if (!isset($jwk['crv'])) {
                    throw new UnexpectedValueException('crv not set');
                }

                if (empty(self::OKP_SUBTYPES[$jwk['crv']])) {
                    throw new DomainException('Unrecognised or unsupported OKP key subtype');
                }

                if (empty($jwk['x'])) {
                    throw new UnexpectedValueException('x not set');
                }

                $publicKey = JWT::convertBase64urlToBase64($jwk['x']);
                return new Key($publicKey, $jwk['alg']);
            default:
                break;
        }

        return null;
    }

    private static function createPemFromCrvAndXYCoordinates(string $crv, string $x, string $y): string
    {
        $pem =
            self::encodeDER(
                self::ASN1_SEQUENCE,
                self::encodeDER(
                    self::ASN1_SEQUENCE,
                    self::encodeDER(
                        self::ASN1_OBJECT_IDENTIFIER,
                        self::encodeOID(self::OID)
                    )
                    . self::encodeDER(
                        self::ASN1_OBJECT_IDENTIFIER,
                        self::encodeOID(self::EC_CURVES[$crv])
                    )
                ) .
                self::encodeDER(
                    self::ASN1_BIT_STRING,
                    \chr(0x00) . \chr(0x04)
                    . JWT::urlsafeB64Decode($x)
                    . JWT::urlsafeB64Decode($y)
                )
            );

        return sprintf(
            "-----BEGIN PUBLIC KEY-----\n%s\n-----END PUBLIC KEY-----\n",
            wordwrap(base64_encode($pem), 64, "\n", true)
        );
    }

    private static function createPemFromModulusAndExponent(
        string $n,
        string $e
    ): string {
        $mod = JWT::urlsafeB64Decode($n);
        $exp = JWT::urlsafeB64Decode($e);

        $modulus = \pack('Ca*a*', 2, self::encodeLength(\strlen($mod)), $mod);
        $publicExponent = \pack('Ca*a*', 2, self::encodeLength(\strlen($exp)), $exp);

        $rsaPublicKey = \pack(
            'Ca*a*a*',
            48,
            self::encodeLength(\strlen($modulus) + \strlen($publicExponent)),
            $modulus,
            $publicExponent
        );

        $rsaOID = \pack('H*', '300d06092a864886f70d0101010500'); // hex version of MA0GCSqGSIb3DQEBAQUA
        $rsaPublicKey = \chr(0) . $rsaPublicKey;
        $rsaPublicKey = \chr(3) . self::encodeLength(\strlen($rsaPublicKey)) . $rsaPublicKey;

        $rsaPublicKey = \pack(
            'Ca*a*',
            48,
            self::encodeLength(\strlen($rsaOID . $rsaPublicKey)),
            $rsaOID . $rsaPublicKey
        );

        return "-----BEGIN PUBLIC KEY-----\r\n" .
            \chunk_split(\base64_encode($rsaPublicKey), 64) .
            '-----END PUBLIC KEY-----';
    }

    private static function encodeLength(int $length): string
    {
        if ($length <= 0x7F) {
            return \chr($length);
        }

        $temp = \ltrim(\pack('N', $length), \chr(0));

        return \pack('Ca*', 0x80 | \strlen($temp), $temp);
    }

    private static function encodeDER(int $type, string $value): string
    {
        $tag_header = 0;
        if ($type === self::ASN1_SEQUENCE) {
            $tag_header |= 0x20;
        }

        $der = \chr($tag_header | $type);

        $der .= \chr(\strlen($value));

        return $der . $value;
    }

    private static function encodeOID(string $oid): string
    {
        $octets = explode('.', $oid);

        $first = (int) array_shift($octets);
        $second = (int) array_shift($octets);
        $oid = \chr($first * 40 + $second);

        foreach ($octets as $octet) {
            if ($octet == 0) {
                $oid .= \chr(0x00);
                continue;
            }
            $bin = '';

            while ($octet) {
                $bin .= \chr(0x80 | ($octet & 0x7f));
                $octet >>= 7;
            }
            $bin[0] = $bin[0] & \chr(0x7f);

            if (pack('V', 65534) == pack('L', 65534)) {
                $oid .= strrev($bin);
            } else {
                $oid .= $bin;
            }
        }

        return $oid;
    }
}
