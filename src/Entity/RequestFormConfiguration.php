<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sulu\Bundle\FormBundle\Entity\Form;

#[ORM\Entity]
class RequestFormConfiguration
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Form::class)]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private Form $form;

    #[ORM\Column(options: ['default' => false])]
    private bool $isRequestForm = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getForm(): Form
    {
        return $this->form;
    }

    public function setForm(Form $form): self
    {
        $this->form = $form;

        return $this;
    }

    public function isRequestForm(): bool
    {
        return $this->isRequestForm;
    }

    public function setIsRequestForm(bool $isRequestForm): self
    {
        $this->isRequestForm = $isRequestForm;

        return $this;
    }
}
