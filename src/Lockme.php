<?php
namespace Lockme\SDK;

use DateTime;
use Exception;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use Lockme\OAuth2\Client\Provider\Lockme as LockmeProvider;
use RuntimeException;

/**
 * Lockme SDK object
 */
class Lockme
{
    /**
     * Lockme OAuth2 provider
     * @var Lockme
     */
    private $provider;
    /**
     * Default access token
     * @var AccessToken
     */
    private $accessToken;

    /**
     * Object constructor
     * @param array $options  Options for Lockme Provider
     */
    public function __construct(array $options=[])
    {
        $this->provider = new LockmeProvider($options);
    }

    /**
     * Generate authorization URL
     * @param  array  $scopes Array of requested scopes
     * @return string         Redirect URL
     */
    public function getAuthorizationUrl($scopes = [])
    {
        $this->provider;
        $authorizationUrl = $this->provider->getAuthorizationUrl([
            'scope' => implode(' ', $scopes)
        ]);
        $_SESSION['oauth2_lockme_state'] = $this->provider->getState();
        return $authorizationUrl;
    }

    /**
     * Get Access token from AuthCode
     * @param  string $code  AuthCode
     * @param  string $state State code
     * @return AccessToken       Access Token
     * @throws Exception
     */
    public function getTokenForCode($code, $state)
    {
        if ($state !== $_SESSION['oauth2_lockme_state']) {
            unset($_SESSION['oauth2_lockme_state']);
            throw new RuntimeException("Wrong state");
        }
        unset($_SESSION['oauth2_lockme_state']);

        $this->accessToken = $this->provider->getAccessToken('authorization_code', [
        'code' => $code
    ]);
        return $this->accessToken;
    }

    /**
     * Refresh access token
     * @param  AccessToken|null  $accessToken  Access token
     * @return AccessToken        Refreshed token
     * @throws IdentityProviderException
     */
    public function refreshToken($accessToken = null)
    {
        $accessToken = $accessToken ?: $this->accessToken;
        $this->accessToken = $this->provider->getAccessToken('refresh_token', [
            'refresh_token' => $accessToken->getRefreshToken()
        ]);
        return $this->accessToken;
    }

    /**
     * Create default access token
     * @param string|AccessToken $token Default access token
     * @return AccessToken
     * @throws Exception
     */
    public function setDefaultAccessToken($token)
    {
        if (is_string($token)) {
            $this->accessToken = new AccessToken(json_decode($token, true));
        } elseif ($token instanceof AccessToken) {
            $this->accessToken = $token;
        } else {
            throw new RuntimeException("Incorrect access token");
        }
        if ($this->accessToken->hasExpired()) {
            $this->refreshToken();
        }
        return $this->accessToken;
    }

    /**
     * Send message to /test endpoint
     * @param  string|AccessToken|null  $accessToken  Access token
     * @return string
     * @throws IdentityProviderException
     */
    public function Test($accessToken = null)
    {
        return $this->provider->executeRequest("GET", "/test", $accessToken ?: $this->accessToken);
    }

    /**
     * Get list of available rooms
     * @param  string|AccessToken|null  $accessToken  Access token
     * @return array
     * @throws IdentityProviderException
     */
    public function RoomList($accessToken = null)
    {
        return $this->provider->executeRequest("GET", "/rooms", $accessToken ?: $this->accessToken);
    }

    /**
     * Get reservation data
     * @param  int  $roomId  Room ID
     * @param  string  $id  Reservation ID
     * @param  string|AccessToken|null  $accessToken  Access token
     * @return array
     * @throws IdentityProviderException
     */
    public function Reservation($roomId, $id, $accessToken = null)
    {
        return $this->provider->executeRequest("GET", "/room/{$roomId}/reservation/{$id}", $accessToken ?: $this->accessToken);
    }

    /**
     * Add new reservation
     * @param array                   $data        Reservation data
     * @param string|AccessToken|null $accessToken Access token
     * @return int
     * @throws Exception
     */
    public function AddReservation($data, $accessToken = null)
    {
        if (!$data['roomid']) {
            throw new RuntimeException("No room ID");
        }
        if (!$data["date"]) {
            throw new RuntimeException("No date");
        }
        if (!$data["hour"]) {
            throw new RuntimeException("No hour");
        }
        return $this->provider->executeRequest("PUT", "/room/{$data['roomid']}/reservation", $accessToken ?: $this->accessToken, $data);
    }

    /**
     * Delete reservation
     * @param  int  $roomId  Room ID
     * @param  string  $id  Reservation ID
     * @param  string|AccessToken|null  $accessToken  Access token
     * @return bool
     * @throws IdentityProviderException
     */
    public function DeleteReservation($roomId, $id, $accessToken = null)
    {
        return $this->provider->executeRequest("DELETE", "/room/{$roomId}/reservation/{$id}", $accessToken ?: $this->accessToken);
    }

    /**
     * Edit reservation
     * @param  int  $roomId  Room ID
     * @param  string  $id  Reservation ID
     * @param  array  $data  Reservation data
     * @param  string|AccessToken|null  $accessToken  Access token
     * @return bool
     * @throws IdentityProviderException
     */
    public function EditReservation($roomId, $id, $data, $accessToken = null)
    {
        return $this->provider->executeRequest("POST", "/room/{$roomId}/reservation/{$id}", $accessToken ?: $this->accessToken, $data);
    }

    /**
     * @param  int  $roomId  Room ID
     * @param  string  $id  Reservation ID
     * @param  array  $data  Move data - array with roomid, date (Y-m-d) and hour (H:i:s)
     * @param  string|AccessToken|null  $accessToken  Access token
     * @return bool
     * @throws IdentityProviderException
     */
    public function MoveReservation($roomId, $id, $data, $accessToken = null)
    {
        return $this->provider->executeRequest("POST", "/room/{$roomId}/reservation/{$id}/move", $accessToken ?: $this->accessToken, $data);
    }

    /**
     * @param  int  $roomId
     * @param  DateTime  $date
     * @param  string|AccessToken|null  $accessToken
     * @return mixed
     * @throws IdentityProviderException
     */
    public function GetReservations($roomId, $date, $accessToken = null)
    {
        return $this->provider->executeRequest("GET", "/room/{$roomId}/reservations/".$date->format("Y-m-d"), $accessToken ?: $this->accessToken);
    }

    /**
     * Get resource owner
     * @param  string|AccessToken|null $accessToken Access token
     * @return ResourceOwnerInterface              Resource owner
     */
    public function getResourceOwner($accessToken = null)
    {
        return $this->provider->getResourceOwner($accessToken ?: $this->accessToken);
    }

    /**
     * Get callback message details
     * @param  int  $messageId  Message ID
     * @param  null  $accessToken
     * @return array
     * @throws IdentityProviderException
     */
    public function GetMessage($messageId, $accessToken = null)
    {
        return $this->provider->executeRequest("GET", "/message/{$messageId}", $accessToken ?: $this->accessToken);
    }

    /**
     * Mark callback message as read
     * @param  int  $messageId  Message ID
     * @param  null  $accessToken
     * @return bool
     * @throws IdentityProviderException
     */
    public function MarkMessageRead($messageId, $accessToken = null)
    {
        return $this->provider->executeRequest("POST", "/message/{$messageId}", $accessToken ?: $this->accessToken);
    }

    /**
     * @param  int  $roomId
     * @param  DateTime  $date
     * @param  null  $accessToken
     * @return array
     * @throws IdentityProviderException
     */
    public function GetDateSettings($roomId, $date, $accessToken = null)
    {
        return $this->provider->executeRequest("GET", "/room/{$roomId}/date/".$date->format("Y-m-d"), $accessToken ?: $this->accessToken);
    }

    /**
     * @param  int  $roomId
     * @param  DateTime  $date
     * @param  array  $settings
     * @param  null  $accessToken
     * @return array
     * @throws IdentityProviderException
     */
    public function SetDateSettings($roomId, $date, $settings, $accessToken = null)
    {
        return $this->provider->executeRequest("POST", "/room/{$roomId}/date/".$date->format("Y-m-d"), $accessToken ?: $this->accessToken, $settings);
    }

    /**
     * @param  int  $roomId
     * @param  DateTime  $date
     * @param  null  $accessToken
     * @return array
     * @throws IdentityProviderException
     */
    public function RemoveDateSettings($roomId, $date, $accessToken = null)
    {
        return $this->provider->executeRequest("DELETE", "/room/{$roomId}/date/".$date->format("Y-m-d"), $accessToken ?: $this->accessToken);
    }

    /**
     * @param  int  $roomId
     * @param  int  $day  0 - Monday, 1 - Tuesday, ..., 6 - Sunday
     * @param  null  $accessToken
     * @return array
     * @throws IdentityProviderException
     */
    public function GetDaySettings($roomId, $day, $accessToken = null)
    {
        return $this->provider->executeRequest("GET", "/room/{$roomId}/day/{$day}", $accessToken ?: $this->accessToken);
    }

    /**
     * @param  int  $roomId
     * @param  int  $day  0 - Monday, 1 - Tuesday, ..., 6 - Sunday
     * @param  array  $settings
     * @param  null  $accessToken
     * @return array
     * @throws IdentityProviderException
     */
    public function SetDaySettings($roomId, $day, $settings, $accessToken = null)
    {
        return $this->provider->executeRequest("POST", "/room/{$roomId}/day/{$day}", $accessToken ?: $this->accessToken, $settings);
    }
}
