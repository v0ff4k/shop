<?php

declare(strict_types=1);

namespace App\Tests\UI\Controller\Api;

use App\Infrastructure\Search\ProductSearchService;
use App\UI\Controller\Api\ProductSuggestController;
use App\UI\Request\ProductSuggestRequestDto;
use Elastica\Client;
use Elastica\Index;
use Elastica\Result;
use Elastica\ResultSet;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class ProductSuggestControllerTest extends TestCase
{
    private Client|MockObject $client;
    private Index|MockObject $index;
    private ProductSuggestController $controller;

    protected function setUp(): void
    {
        $this->client = $this->createMock(Client::class);
        $this->index = $this->createMock(Index::class);

        $this->client
            ->method('getIndex')
            ->with('products')
            ->willReturn($this->index);

        $this->controller = new ProductSuggestController(new ProductSearchService($this->client));
    }

    public function testReturnsSuggestions(): void
    {
        $resultSet = $this->createMock(ResultSet::class);
        $first = $this->createMock(Result::class);
        $second = $this->createMock(Result::class);

        $first
            ->method('getSource')
            ->willReturn(['title' => 'plastic e-gold']);
        $second
            ->method('getSource')
            ->willReturn(['title' => 'plastic cups']);

        $resultSet
            ->method('getResults')
            ->willReturn([$first, $second]);

        $this->index
            ->expects($this->once())
            ->method('search')
            ->willReturn($resultSet);

        $dto = ProductSuggestRequestDto::fromRequest(new Request(['q' => 'pla', 'limit' => '5']));
        $response = ($this->controller)($dto);

        $this->assertSame(200, $response->getStatusCode());

        $payload = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame(['plastic e-gold', 'plastic cups'], $payload['suggestions']);
    }

    public function testQueryDtoFromRequest(): void
    {
        $dto = ProductSuggestRequestDto::fromRequest(new Request(['q' => '  plastic  ', 'limit' => '50']));

        $this->assertSame('plastic', $dto->query);
        $this->assertSame(10, $dto->limit);
    }
}
