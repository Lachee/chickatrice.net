<?php namespace kiss\helpers;

use kiss\exception\ArgumentException;
use kiss\exception\HttpException;
use kiss\Kiss;
use kiss\models\Identity;

class Scope {
    /**
     * Checks if the identity meets all the requirements
     * @param Identity $identity the user we are validating against
     * @param array $scopes the scopes we need
     * @return bool true if they are authorized
     * @throws ArgumentException 
     */
    public static function authenticate($identity, $scopes) {
            
        if ($scopes === null) return true;
        if ($identity == null) return false;
        
        //Verify the identity
        if (!($identity instanceof Identity)) 
            throw new ArgumentException('invalid type of identity');

        //Get authentication
        $auth = $identity->authorization();
        if ($auth == null) return false;

        //Verify its identity. This is a critical error because it means the user isn't from here
        if ($auth->sub != $identity->uuid)
            throw new HttpException(HTTP::FORBIDDEN, 'CRITICAL: JWT does not belong to identity');

        //Verify its origins
        if ($auth->iss != Kiss::$app->baseURL()) 
            return false;

        $success = true;
        $control = [];
        
        //Verify each scope
        foreach($scopes as $scope) {
            if (Strings::startsWith($scope, 'jwt:')) {
                $parts = explode(':', $scope);
                $value = Arrays::value($auth, $parts[1], null);
                if ($value === null) return false;
                if ($value != $parts[2]) return false;
                continue;
            }
        
            if (Strings::startsWith($scope, 'ctrl:')) {
                $parts = explode(':', $scope, 3);
                $control[$parts[1]] = $parts[3] ?? true;
                continue;
            }

            $scopes = Arrays::value($auth, 'scopes', []);
            if (!in_array($scope, $scopes)) $success = false;
        }

        //If we are login scoped and we have allowed users specifically then allow all
        if (Arrays::value($control, 'allow_users', false) && $auth->src == 'user') 
            $success = true;

        //Return the results
        return $success;
    }

    /** Checks if the identity has the correct scope
     * @param Identity $identity the user we are validating against
     * @param string $scope the scope
     * @return bool
     */
    public static function check($identity, $scope) {
        
        if ($identity == null) return false;
        if (!($identity instanceof Identity)) 
            throw new ArgumentException('invalid type of identity');

        $auth = $identity->authorization();
        $scopes = Arrays::value($auth, 'scopes', []);
        return in_array($scope, $scopes);
    }
}