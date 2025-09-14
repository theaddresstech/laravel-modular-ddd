<?php

declare(strict_types=1);

namespace Modules\ProductCatalog\Presentation\Http\Controllers;

use Modules\ProductCatalog\Application\Commands\CreateProduct\CreateProductCommand;
use Modules\ProductCatalog\Application\Commands\CreateProduct\CreateProductHandler;
use Modules\ProductCatalog\Application\Commands\UpdateProduct\UpdateProductCommand;
use Modules\ProductCatalog\Application\Commands\UpdateProduct\UpdateProductHandler;
use Modules\ProductCatalog\Application\Commands\PublishProduct\PublishProductCommand;
use Modules\ProductCatalog\Application\Commands\PublishProduct\PublishProductHandler;
use Modules\ProductCatalog\Application\Queries\GetProduct\GetProductQuery;
use Modules\ProductCatalog\Application\Queries\GetProduct\GetProductHandler;
use Modules\ProductCatalog\Application\Queries\ListProducts\ListProductsQuery;
use Modules\ProductCatalog\Application\Queries\ListProducts\ListProductsHandler;
use Modules\ProductCatalog\Presentation\Http\Requests\CreateProductRequest;
use Modules\ProductCatalog\Presentation\Http\Requests\UpdateProductRequest;
use Modules\ProductCatalog\Presentation\Http\Resources\ProductResource;
use Modules\ProductCatalog\Presentation\Http\Resources\ProductCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;

class ProductController extends Controller
{
    public function __construct(
        private CreateProductHandler $createProductHandler,
        private UpdateProductHandler $updateProductHandler,
        private PublishProductHandler $publishProductHandler,
        private GetProductHandler $getProductHandler,
        private ListProductsHandler $listProductsHandler
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = new ListProductsQuery(
            filters: $request->get('filters', []),
            search: $request->get('search'),
            categoryId: $request->get('category_id'),
            status: $request->get('status'),
            sortBy: $request->get('sort_by', 'created_at'),
            sortDirection: $request->get('sort_direction', 'desc'),
            page: $request->integer('page', 1),
            perPage: min($request->integer('per_page', 15), 100)
        );

        $result = $this->listProductsHandler->handle($query);

        return (new ProductCollection($result['products']))
            ->additional([
                'meta' => [
                    'current_page' => $result['pagination']['current_page'],
                    'per_page' => $result['pagination']['per_page'],
                    'total' => $result['pagination']['total'],
                    'last_page' => $result['pagination']['last_page'],
                    'from' => $result['pagination']['from'],
                    'to' => $result['pagination']['to'],
                ],
                'filters_applied' => $query->getAppliedFilters(),
            ])
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function show(string $id): JsonResponse
    {
        $query = new GetProductQuery($id);
        $product = $this->getProductHandler->handle($query);

        if (!$product) {
            return response()->json([
                'message' => 'Product not found',
                'error' => 'PRODUCT_NOT_FOUND',
            ], Response::HTTP_NOT_FOUND);
        }

        return (new ProductResource($product))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function store(CreateProductRequest $request): JsonResponse
    {
        $command = new CreateProductCommand(
            name: $request->validated('name'),
            description: $request->validated('description'),
            priceAmount: $request->validated('price_amount'),
            currency: $request->validated('currency'),
            categoryId: $request->validated('category_id'),
            images: $request->validated('images', []),
            attributes: $request->validated('attributes', [])
        );

        try {
            $product = $this->createProductHandler->handle($command);

            return (new ProductResource($product))
                ->additional([
                    'message' => 'Product created successfully'
                ])
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'error' => 'VALIDATION_ERROR',
                'details' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create product',
                'error' => 'CREATION_FAILED',
                'details' => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateProductRequest $request, string $id): JsonResponse
    {
        $command = new UpdateProductCommand(
            id: $id,
            name: $request->validated('name'),
            description: $request->validated('description'),
            priceAmount: $request->validated('price_amount'),
            currency: $request->validated('currency'),
            categoryId: $request->validated('category_id'),
            images: $request->validated('images'),
            attributes: $request->validated('attributes')
        );

        try {
            $product = $this->updateProductHandler->handle($command);

            return (new ProductResource($product))
                ->additional([
                    'message' => 'Product updated successfully'
                ])
                ->response()
                ->setStatusCode(Response::HTTP_OK);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'error' => 'VALIDATION_ERROR',
                'details' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update product',
                'error' => 'UPDATE_FAILED',
                'details' => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        // Implementation would depend on your business rules
        // You might want to soft delete or archive instead of hard delete

        return response()->json([
            'message' => 'Product deletion not implemented',
            'error' => 'NOT_IMPLEMENTED',
        ], Response::HTTP_NOT_IMPLEMENTED);
    }

    public function publish(string $id): JsonResponse
    {
        $command = new PublishProductCommand($id);

        try {
            $product = $this->publishProductHandler->handle($command);

            return (new ProductResource($product))
                ->additional([
                    'message' => 'Product published successfully'
                ])
                ->response()
                ->setStatusCode(Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to publish product',
                'error' => 'PUBLISH_FAILED',
                'details' => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function unpublish(string $id): JsonResponse
    {
        // Similar implementation to publish but for unpublishing
        return response()->json([
            'message' => 'Product unpublish not implemented',
            'error' => 'NOT_IMPLEMENTED',
        ], Response::HTTP_NOT_IMPLEMENTED);
    }

    public function search(Request $request): JsonResponse
    {
        $searchTerm = $request->get('q', '');
        if (empty($searchTerm)) {
            return response()->json([
                'message' => 'Search term is required',
                'error' => 'SEARCH_TERM_REQUIRED',
            ], Response::HTTP_BAD_REQUEST);
        }

        $query = new ListProductsQuery(
            search: $searchTerm,
            status: 'published', // Only search published products
            perPage: min($request->integer('per_page', 10), 50)
        );

        $result = $this->listProductsHandler->handle($query);

        return (new ProductCollection($result['products']))
            ->additional([
                'meta' => [
                    'search_term' => $searchTerm,
                    'total_results' => $result['pagination']['total'],
                ],
            ])
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function featured(Request $request): JsonResponse
    {
        $query = new ListProductsQuery(
            filters: ['featured' => true],
            status: 'published',
            perPage: min($request->integer('per_page', 10), 20)
        );

        $result = $this->listProductsHandler->handle($query);

        return (new ProductCollection($result['products']))
            ->additional([
                'meta' => [
                    'type' => 'featured',
                    'total' => $result['pagination']['total'],
                ],
            ])
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}