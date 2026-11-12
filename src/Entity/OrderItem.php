<?php

namespace App\Entity;

use App\Repository\OrderItemRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Maps the legacy `order_items` table 1:1. One row per purchased unit
 * (the legacy cart stored one order_item per quantity unit).
 */
#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
#[ORM\Table(name: 'order_items')]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    /** FK with ON DELETE CASCADE — deleting an order removes its items. */
    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Order $order = null;

    /** FK with ON DELETE SET NULL — keeps the snapshot when a product is deleted. */
    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Product $product = null;

    #[ORM\Column(name: 'item_type', type: 'string', length: 100)]
    private string $itemType = '';

    #[ORM\Column(name: 'button_color', type: 'string', length: 50, nullable: true)]
    private ?string $buttonColor = null;

    #[ORM\Column(name: 'artwork_path', type: 'string', length: 255, nullable: true)]
    private ?string $artworkPath = null;

    #[ORM\Column(name: 'price', type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $price = '0.00';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): static
    {
        $this->order = $order;

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getItemType(): string
    {
        return $this->itemType;
    }

    public function setItemType(string $itemType): static
    {
        $this->itemType = $itemType;

        return $this;
    }

    public function getButtonColor(): ?string
    {
        return $this->buttonColor;
    }

    public function setButtonColor(?string $buttonColor): static
    {
        $this->buttonColor = $buttonColor;

        return $this;
    }

    public function getArtworkPath(): ?string
    {
        return $this->artworkPath;
    }

    public function setArtworkPath(?string $artworkPath): static
    {
        $this->artworkPath = $artworkPath;

        return $this;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;

        return $this;
    }
}
