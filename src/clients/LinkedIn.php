<?php
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\authclient\clients;

use yii\authclient\OAuth2;
use yii\web\HttpException;
use Yii;

/**
 * LinkedIn allows authentication via LinkedIn OAuth.
 *
 * In order to use linkedIn OAuth you must register your application at <https://www.linkedin.com/secure/developer>.
 *
 * Example application configuration:
 *
 * ```php
 * 'components' => [
 *     'authClientCollection' => [
 *         'class' => 'yii\authclient\Collection',
 *         'clients' => [
 *             'linkedin' => [
 *                 'class' => 'yii\authclient\clients\LinkedIn',
 *                 'clientId' => 'linkedin_client_id',
 *                 'clientSecret' => 'linkedin_client_secret',
 *                 'useOpenId' => true,
 *             ],
 *         ],
 *     ]
 *     // ...
 * ]
 * ```
 *
 * @see https://developer.linkedin.com/docs/oauth2
 * @see https://www.linkedin.com/secure/developer
 * @see https://developer.linkedin.com/docs/rest-api
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0
 */
class LinkedIn extends OAuth2
{
    /**
     * {@inheritdoc}
     */
    public $authUrl = 'https://www.linkedin.com/oauth/v2/authorization';
    /**
     * {@inheritdoc}
     */
    public $tokenUrl = 'https://www.linkedin.com/oauth/v2/accessToken';
    /**
     * {@inheritdoc}
     */
    public $apiBaseUrl = 'https://api.linkedin.com/v2';
    /**
     * @var array list of attribute names, which should be requested from API to initialize user attributes.
     * @since 2.0.4
     */
    public $attributeNames = [
        'id',
        'firstName',
        'lastName',
    ];
    /**
     * @var bool whether the new OpenId api is to be used. The old version scopes are deprecated. Any new LinkedIn auth app can only use OpenId
     * @since 2.2.17
     */
    public $useOpenId = false;

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();
        if ($this->scope === null) {
            $scopes = [
                'r_liteprofile',
                'r_emailaddress',
            ];
            if($this->useOpenId) {
                $scopes = [
                    'openid',
                    'email',
                    'profile'
                ];
            }
            $this->scope = implode(' ', $scopes);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function defaultNormalizeUserAttributeMap()
    {
        if($this->useOpenId) {
            return [
                'id' => function ($attributes) {
                    return $attributes['sub'];
                },
                'first_name' => function ($attributes) {
                    return $attributes['given_name'];
                },
                'last_name' => function ($attributes) {
                    return $attributes['family_name'];
                },
            ];
        }

        return [
            'first_name' => function ($attributes) {
                return array_values($attributes['firstName']['localized'])[0];
            },
            'last_name' => function ($attributes) {
                return array_values($attributes['lastName']['localized'])[0];
            },
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function initUserAttributes()
    {
        if($this->useOpenId) {
            return $this->api('userinfo');
        }

        $attributes = $this->api('me?projection=(' . implode(',', $this->attributeNames) . ')', 'GET');
        $scopes = explode(' ', $this->scope);
        if (in_array('r_emailaddress', $scopes, true)) {
            $emails = $this->api('emailAddress?q=members&projection=(elements*(handle~))', 'GET');
            if (isset($emails['elements'][0]['handle~']['emailAddress'])) {
                $attributes['email'] = $emails['elements'][0]['handle~']['emailAddress'];
            }

        }

        return $attributes;
    }

    /**
     * {@inheritdoc}
     */
    public function applyAccessTokenToRequest($request, $accessToken)
    {
        $data = $request->getData();
        $data['oauth2_access_token'] = $accessToken->getToken();
        $request->setData($data);
    }

    /**
     * {@inheritdoc}
     */
    protected function defaultName()
    {
        return 'linkedin';
    }

    /**
     * {@inheritdoc}
     */
    protected function defaultTitle()
    {
        return 'LinkedIn';
    }
}
