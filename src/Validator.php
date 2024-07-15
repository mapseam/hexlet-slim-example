<?php

namespace App;

class Validator
{
    public function validate(array $user)
    {
        $errors = [];

        if ($user['name'] === '') {
            $errors['name'] = "Can't be blank";
        } else if ($user['email'] === '') {
            $errors['email'] = "Can't be blank";
        } else if (!str_contains($user['email'], '@')) {
            $errors['email'] = 'Invalid e-mail address!';
        }

        /*if (mb_strlen($user['name']) <= 4) {
            $errors['name'] = "Nickname must be greater than 4 characters";
        }*/

        return $errors;
    }
}