<?php

namespace App;

class User
{
    public $id;
    public $email;
    public $password;

    public function __construct($id, $email, $password)
    {
        $this->id = $id;
        $this->email = $email;
        $this->password = $password;
    }
}
