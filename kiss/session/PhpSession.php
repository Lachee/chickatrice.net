<?php namespace kiss\session;

use kiss\exception\InvalidOperationException;

class PhpSession extends Session {


    /** {@inheritdoc} */
    public function start() {

        //Set the previous session ID. This would be from the JWT
        $sid = $this->getSessionId();
        session_id($sid);

        //Start the session, we dont want to use cookies. They are stinky.
        if (!session_start([ 'use_cookies' => false ]))
            throw new InvalidOperationException();

        //Set the new session id
        return $this->setSessionId(session_id());
    }

    /** {@inheritdoc} */
    public function reset() {
        if (!session_abort())
            throw new InvalidOperationException();
        return $this;
    }

    /** {@inheritdoc} */
    public function stop() {
        session_destroy();
        $this->setSessionId(null);
        return $this;
    }

    /** {@inheritdoc} */
    public function get($key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }

    /** {@inheritdoc} */
    public function set($key, $value) {
        $_SESSION[$key] = $value;
        return true;
    }

    /** {@inheritdoc} */
    public function delete($key) {
        if (!isset($key)) return false;
        unset($_SESSION[$key]);
        return true;
    }

    /** {@inheritdoc} */
    public function isset($key) {
        return isset($_SESSION[$key]);
    }
}