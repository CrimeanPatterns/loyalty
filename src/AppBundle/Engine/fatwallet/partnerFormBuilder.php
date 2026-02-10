<?php

use AwardWallet\MainBundle\Entity\Usr;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints;

function fatwalletPartnerFormBuilder(FormBuilderInterface $builder, array $options, Usr $user)
{
    $builder->add('Login', 'text', [
        'label'       => 'Email',
        'required'    => true,
        'constraints' => [
            new Constraints\NotBlank(),
            new Constraints\Email(),
            new Constraints\Length(['min'=> 1, 'max' => 80]),
        ],
        'data' => $user->getEmail(),
    ]);

    $builder->add('Pass', 'text', [
        'label'       => 'Password',
        'required'    => true,
        'constraints' => [
            new Constraints\NotBlank(),
            new Constraints\Length(['min'=> 1, 'max' => 80]),
        ],
        'data' => RandomStr(ord('a'), ord('z'), 10),
    ]);

    $builder->add('Agree', 'checkbox', [
        'label'       => "I have read the <a href='http://www.fatwallet.com/useragreement.php' target='_blank'>terms and conditions</a>",
        'required'    => true,
        'constraints' => [
            new Constraints\NotBlank(['message'=>"You must agree to FatWallet User Agreement and Privacy Policy"]),
        ],
        'value' => 1,
    ]);
}
