<?php

namespace App;

class FaceModel
{
    public $id;
    public $user_id;
    public $features;

    public function __construct($id, $user_id, $features)
    {
        $this->id = $id;
        $this->user_id = $user_id;
        $this->features = $features;
    }
}