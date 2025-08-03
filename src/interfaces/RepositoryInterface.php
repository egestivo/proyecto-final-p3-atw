<?php

declare(strict_types=1);

namespace App\interfaces;

interface RepositoryInterface{
    public function create(object $entity): bool;
    public function findByid(int $id): ?object;//no se sabe que va a devolver

    public function update(object $entity): bool;
    public function delete(int $id): bool;

    public function findAll(): array;//devuelve un arreglo de entidades

}