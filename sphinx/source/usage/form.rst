.. index::
   single: form

Form
====

Create `Data` class for your entity, for example `src/Data/UserData.php`

This class intended for use as class for form instead of using entity class. It usually not content setter methods and data must be handed over through controller.

Validation rules also defined there.

Example:

::

  <?php
  
  namespace App\Data;
  
  use Doctrine\Common\Collections\Collection;
  use Symfony\Component\Validator\Mapping\ClassMetadata;
  use Symfony\Component\Validator\Constraints as Assert;
  
  class UserData
  {
      private $name;
      private $login;
      private $password;
      private $curPassword; // for changing password
      private $roles;
  
      public function __construct(
          $name,
          $login,
          $password,
          $roles
      ) {
          $this->name = $name;
          $this->login = $login;
          $this->password = $password;
          $this->roles = $roles;
      }
  
      // validation rules
      public static function loadValidatorMetadata(ClassMetadata $metadata) {
          $metadata->addPropertyConstraint('name', new Assert\NotBlank());
          $metadata->addPropertyConstraint('login', new Assert\NotBlank());
          $metadata->addPropertyConstraint('password', new Assert\NotBlank(['groups' => ['add']]));
          $metadata->addPropertyConstraint('roles', new Assert\NotBlank());
      }
  
      public function getName(): ?string {
          return $this->name;
      }
  
      public function getLogin(): ?string {
          return $this->login;
      }
  
      public function getPassword(): string {
          return (string)$this->password;
      }
  
      public function getCurPassword(): string {
          return (string) $this->curPassword;
      }
  
      public function setCurPassword(?string $curPassword): self {
          $this->curPassword = $curPassword;
          return $this;
      }
  
      public function getRoles(): array {
          return ($this->roles and is_array($this->roles)) ? $this->roles : [];
      }
  }

It's recommended to define separate methods for manage form, because it allows to write specific logic. For example, for user before saving in database password must be hashed. To do this define in UserController method `beforeSave`:

::

  use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
  .
  .
  .
  
  
      public function beforeSave($obj, $request, $formData) {
          if ($formData->getPassword()) {
              // password encode
              $obj->setPassword($this->passwordHasher->hashPassword($obj, $formData->getPassword()));
          }
      }

