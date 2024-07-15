<?php

namespace App;

class UserRepository
{
    private function fillRepo()
    {
        $usersArr = [
            ['name' => 'admin', 'email' => 'admin@hexlet.io', 'passwordDigest' => hash('sha256', 'secret')],
            ['name' => 'mike', 'email' => 'mike@hexlet.io', 'passwordDigest' => hash('sha256', 'superpass')],
            ['name' => 'mishel', 'email' => 'mishel@hexlet.io', 'passwordDigest' => hash('sha256', 'strongpass')],
            ['name' => 'adel', 'email' => 'adel@hexlet.io', 'passwordDigest' => hash('sha256', 'secret')],
            ['name' => 'keks', 'email' => 'keks@hexlet.io', 'passwordDigest' => hash('sha256', 'superpass')],
            ['name' => 'kamila', 'email' => 'kamila@hexlet.io', 'passwordDigest' => hash('sha256', 'strongpass')]
        ];
        
        foreach ($usersArr as $user) {
            $this->save($user);
        }
    }

    public function __construct()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!array_key_exists('users', $_SESSION)) {
            $_SESSION['users'] = [];
        }

        $this->fillRepo();
    }

    public function destroy(string $id)
    {
        unset($_SESSION['users'][$id]);
    }

    public function all()
    {
        return array_values($_SESSION['users']);
    }

    public function find(string $id)
    {
        if (!isset($_SESSION['users'][$id])) {
            throw new \Exception("Wrong user id: {$id}");
        }

        return $_SESSION['users'][$id];
    }

    public function save(array &$item)
    {
        if (empty($item['name']) || empty($item['email'])) {
            $json = json_encode($item);
            throw new \Exception("Wrong data: {$json}");
        }
                
        $doublesCounter = 0;
        foreach ($_SESSION['users'] as $user) {
            if (($user['name'] === $item['name']) || ($user['email'] === $item['email'])) {
                $doublesCounter += 1;
            }
        }

        $id = "";
        if ($doublesCounter === 0) {
            if (!isset($item['id'])) {
                $id = uniqid();
                $item['id'] = $id;
            }
    
            $_SESSION['users'][$item['id']] = $item;
        }

        return $id;
    }
}
