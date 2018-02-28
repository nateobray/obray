<?php
namespace obray;

if (!class_exists( 'obray\oObject' )) { die(); }

/**
 * Class used for logging/debugging
 */

class oSession {

    public function start($name, $limit=0, $path='/', $domain=null, $secure=null)
    {
        // Set the cookie name before we start.
        session_name($name . '_Session');

        // Set the domain to default to the current domain.
        $domain = isset($domain) ? $domain : isset($_SERVER['SERVER_NAME']);

        // Set the default secure value to whether the site is being accessed with SSL
        $https = isset($secure) ? $secure : isset($_SERVER['HTTPS']);

        // Set the cookie settings and start the session
        \session_set_cookie_params($limit, $path, $domain, $secure, true);
        \session_start();
        
        // Make sure the session hasn't expired, and destroy it if it has
        if ($this->validateSession()) {
            // Check to see if the session is new or a hijacking attempt
            if (!$this->preventHijacking()) {
                
                print_r("Session hijack regeneration");
                $this->regenerateSession();
                // Reset session data and regenerate id
                $_SESSION = array();
                $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
                $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];

            // Give a 5% chance of the session id changing on any request
            } elseif (rand(1, 100) <= 5) {
                print_r("Random regeneration");
                $this->regenerateSession();
            }
        }else{
            $_SESSION = array();
            \session_destroy();
            \session_start();
        }
        \session_write_close();

    }

    private function regenerateSession()
    {
        // If this session is obsolete it means there already is a new id
        if(isset($_SESSION['OBSOLETE']) && $_SESSION['OBSOLETE'] == true)
        return;

        // Set current session to expire in 10 seconds
        $_SESSION['OBSOLETE'] = true;
        $_SESSION['EXPIRES'] = time() + 10;

        // Create new session without destroying the old one
        $begin_session_id = session_id();;
        print_r($begin_session_id."\n");
        $regen_success = \session_regenerate_id(false);
        if( $regen_success ){
            print_r("Regen Success\n");
            $end_session_id = session_id();
            print_r($end_session_id."\n");
        } else {
            print_r("Regen Failure\n");
        }

        // Grab current session ID and close both sessions to allow other scripts to use them
        $newSession = session_id();
        \session_write_close();
        print_r("new session_id: ".$newSession."\n");
        
        // Set session ID to the new one, and start it back up again
        \session_id($newSession);
        \session_start();
        print_r("new new session_id:".session_id()."\n");
        $_SESSION['OBSOLETE'] = false;
        $_SESSION['EXPIRES'] = strtotime("now +30 minutes");
        print_r($_SESSION);
        \session_write_close();

    }

    private function validateSession()
    {
        if( isset($_SESSION['OBSOLETE']) && !isset($_SESSION['EXPIRES']) )
            return false;

        if(isset($_SESSION['EXPIRES']) && $_SESSION['EXPIRES'] < time())
            return false;

        return true;
    }

    /**
     * overrides magic set method to enable oSession->$key write functionality
     *
     * @param string $key
     * @param mixed $value
     *
     * @return mixed
     */
    
    public function __set($key, $value)
    {
        \session_start();
        $_SESSION[$key] = $value;
        \session_write_close();
    }

    /**
     * overrides magic get method to enable oSession->$key read functionality
     *
     * @param string $key
     *
     * @return mixed
     */

    public function __get($key)
    {
        \session_start(['read_and_close'=>1]);
        if (isSet($_SESSION[$key])) {
            return $_SESSION[$key];
        } else {
            throw new \obray\exceptions\InvalidSessionKey("Invalid session key.", 501);
        }
    }

    /**
     * overrides magic get method to enable isset(oSession->$key) read functionality
     *
     * @param string $key
     *
     * @return bool
     */

    public function __isset($key)
    {
        \session_start(['read_and_close'=>1]);
        return isset($_SESSION[$key]);
    }

    /**
     * overrides magic unset method to enable unset(oSession->$key) write functionality
     *
     * @param string $key
     *
     * @return bool
     */

    public function __unset($key)
    {
        \session_start();
        unset( $_SESSION[$name] );
        \session_write_close();
    }

    /**
     * destroys a session, good for when we want to log-off a user
     *
     * @return void
     */

    public function destroy()
    {
        \session_destroy();
    }

    public function preventHijacking()
    {
        print_r($_SESSION);
        if (!isset($_SESSION['ip_address']) || !isset($_SESSION['user_agent'])) {
            print_r("Session variables not set.");
            return false;
        }
        
        if ($_SESSION['ip_address'] != $_SERVER['REMOTE_ADDR']) {
            print_r("IP Address invalid.");
            return false;
        }
        
        if ($_SESSION['user_agent'] != $_SERVER['HTTP_USER_AGENT']) {
            print_r("User agent invalid.");
            return false;
        }
        
        return true;
    }

}   