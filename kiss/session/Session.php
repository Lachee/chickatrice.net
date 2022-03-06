<?php

namespace kiss\session;

use Firebase\JWT\ExpiredException;
use kiss\exception\ArgumentException;
use kiss\exception\InvalidOperationException;
use kiss\helpers\HTML;
use kiss\helpers\HTTP;
use kiss\Kiss;
use kiss\models\BaseObject;

abstract class Session extends BaseObject {

    /** @var string the cookie name */
    private const JWT_COOKIE_NAME = '_KISSJWT';
    
    /** @var string the name of the notification session */
    private const KEY_NOTIFICATIONS = '$notifications';

    /** @var string current session id */
    private $session_id;

    /** @var string the current JWT */
    private $jwt;

    /** @var object session claims from the JWT */
    private $claims = null;

    /** @var int how long sessions last for in seconds by default. */
    public $sessionDuration = 24*60*60;

    /** Initializes the session from JWT. Throws if unable. */
    protected function init() {
        try { 
            $jwt = HTTP::cookie(self::JWT_COOKIE_NAME, null);
            if ($jwt == null) $this->clearJWT();
            else $this->setJWT($jwt, false);
        }
        catch(ArgumentException $e) { }
        catch(ExpiredException $e) { }
    }

    /** @return string the current JWT */
    public function getJWT() { return $this->jwt; }

    /** @return object the current JWT claims. */
    public function getClaims() { return $this->claims; }

    /** @return object get a claim from the JWT, otherwise returns default. */
    public function getClaim($claim, $default = null) { 
        if ($this->hasClaim($claim)) return $this->claims->{$claim};
        return $default;
    }

    /** @return bool checks if a claim exists */
    public function hasClaim($claim) {
        return property_exists($this->claims, $claim);
    }

    /** clears the JWT */
    public function clearJWT() {
        HTTP::setCookie(self::JWT_COOKIE_NAME, null);
        $this->jwt = null;
    }

    /** Sets the current JWT. If the session details reset then the current session will be aborted. */
    public function setJWT($jwt, $destroySession = true) {
        if ($jwt == null) throw new ArgumentException('No valid JWT');
        $this->jwt = $jwt;

        $this->claims = Kiss::$app->jwtProvider->decode($this->jwt);
        if (empty($this->claims->sid)) throw new InvalidOperationException("Invalid Token");

        $previousSessionId = $this->session_id;
        $this->session_id = $this->claims->sid;

        //Start-Stop because there is a difference in id
        if ($destroySession && $previousSessionId != $this->session_id) 
            $this->reset()->start();
        

        //Store the JWT
        HTTP::setCookie(self::JWT_COOKIE_NAME, $this->jwt, [ HTTP::COOKIE_EXPIRES => $this->claims->exp, HTTP::COOKIE_PATH => '/' ]);
    }

    /** Gets the current session ID.
     * @return string|null the session id, null if there is none available.
     */
    public function getSessionId() {
        return $this->session_id;
    }

    /** Sets the current session ID */
    protected function setSessionId($sid) {

        //set the SID. Doing so now will prevent us needless restarting the session
        $this->session_id = $sid;

        if ($sid == null) 
        {
            //Clear the cookie
            $this->claims   = null;
            $this->jwt      = null;
            HTTP::setCookie(self::JWT_COOKIE_NAME, 'expire', [ HTTP::COOKIE_EXPIRES => -3600, HTTP::COOKIE_PATH => '/'  ]);
        } 
        else 
        {
            //Convert our previous claims
            $claims = (array) $this->claims;
            $claims['sid'] = $sid;

            //Create a new JWT based of these claims and apply it.
            $jwt = Kiss::$app->jwtProvider->encode($claims, $this->sessionDuration);
            $this->setJWT($jwt);
        }
        return $this;
    }


    /** Creates a session, or resumes an existing one.
     * @return Session this
     */
    public abstract function start();

    /** Finishes the session without saving any pending data. Behalves like session_abort.
     * @return Session this
     */
    public abstract function reset();

    /** Destroys a session and clears all its data.
     * @return Session this
    */
    public abstract function stop();

    /** Gets a session value 
     * @param string $key the key in the session
     * @param mixed $default the default value to return
     * @return mixed the value, otherwise default.
    */
    public abstract function get($key, $default = null);
    
    /** Sets a session key
     * @param string $key the key in the session
     * @param mixed $value the value to store. Not every implementation of session may support complex objects, so it is recommended to only store simple strings or hashmaps.
     * @return bool true if setting was sucessful.
      */
    public abstract function set($key, $value);

    /** Deletes a key.
     * @param string $key the key to delete
     * @return bool true if it was deleted
     */
    public abstract function delete($key);

    /** Checks if a session key is set
     * @param string $key the key in the session
     * @return bool true if it is set
     */
    public abstract function isset($key);

    /** Adds a notification.
     * @param string|string[] notifications
     */
    public function addNotification($notification, $type = 'info') {
        if (!is_array($notification)) { $notification = [$notification]; }

        foreach($notification as $notif) {
            $notifications = $this->get(self::KEY_NOTIFICATIONS, []);
            $notifications[] = [ 'content' => HTML::encode($notif), 'raw' => $notif, 'type' => $type ];
            $this->set(self::KEY_NOTIFICATIONS, $notifications);
        }
    }

    /** Fetches all notifications and clears the list */
    public function consumeNotifications() {
        $notifications = $this->get(self::KEY_NOTIFICATIONS, []);
        $this->set(self::KEY_NOTIFICATIONS, []);
        return $notifications;
    }
}