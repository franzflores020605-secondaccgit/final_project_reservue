<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class ContactFormSubmission
{
    public function __construct(
        #[Assert\NotBlank(message: 'Please enter your name.')]
        #[Assert\Length(max: 200)]
        public string $name = '',
        #[Assert\NotBlank(message: 'Please enter your email.')]
        #[Assert\Email(message: 'Please enter a valid email address.')]
        public string $email = '',
        #[Assert\NotBlank(message: 'Please enter a message.')]
        #[Assert\Length(max: 5000, maxMessage: 'Message is too long (max {{ limit }} characters).')]
        public string $message = '',
    ) {
    }
}
