<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Data\DTO;

final class Customer
{
    public string $email;
    public ?string $name;
    public ?string $phonenumber;

    public function __construct(string $email, ?string $name = null, ?string $phonenumber = null)
    {
        $this->email = $email;
        $this->name = $name;
        $this->phonenumber = $phonenumber;
    }

    public function toArray(): array
    {
        $data = ['email' => $this->email];
        
        if ($this->name !== null) {
            $data['name'] = $this->name;
        }
        
        if ($this->phonenumber !== null) {
            $data['phonenumber'] = $this->phonenumber;
        }
        
        return $data;
    }

    public static function make(string $email, ?string $name = null, ?string $phonenumber = null): self
    {
        return new self($email, $name, $phonenumber);
    }
}