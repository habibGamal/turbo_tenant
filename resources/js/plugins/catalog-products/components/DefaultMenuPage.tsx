import React from 'react';
import { useSearchParams } from 'react-router-dom';
import { useInfiniteCatalogProducts, useCatalogCategories } from '../api';

export default function DefaultMenuPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const search = searchParams.get('search') || '';
  const categoryIds = searchParams.getAll('category_ids[]')
    .concat(searchParams.getAll('category_ids'))
    .concat(searchParams.getAll('category[]'))
    .concat(searchParams.getAll('category'))
    .map(Number)
    .filter((n) => !isNaN(n) && n > 0);
  const sortBy = searchParams.get('sort_by') || 'name';
  const sortOrder = searchParams.get('sort_order') || 'asc';

  const {
    data,
    isLoading,
    isError,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useInfiniteCatalogProducts({
    search: search || undefined,
    category_ids: categoryIds.length > 0 ? categoryIds : undefined,
    sort_by: sortBy,
    sort_order: sortOrder,
  });

  const { data: categoriesData } = useCatalogCategories();
  const allProducts = data?.pages.flatMap((page) => page.data) || [];

  return (
    <div className="container mx-auto px-4 py-8" data-testid="default-menu-page">
      <h1 className="text-3xl font-bold mb-6">Menu</h1>
      {isLoading ? (
        <div className="py-12 text-center text-muted-foreground">Loading products...</div>
      ) : isError ? (
        <div className="py-12 text-center text-destructive">Failed to load menu products.</div>
      ) : allProducts.length === 0 ? (
        <div className="py-12 text-center text-muted-foreground">No products found.</div>
      ) : (
        <div>
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            {allProducts.map((p) => (
              <div key={p.id} className="border rounded-lg p-4 flex flex-col justify-between">
                <div>
                  <h3 className="font-semibold text-lg">{p.name}</h3>
                  {p.description && <p className="text-sm text-gray-500 line-clamp-2">{p.description}</p>}
                </div>
                <div className="mt-4 flex items-center justify-between">
                  <span className="font-bold">{p.price.toFixed(2)} EGP</span>
                </div>
              </div>
            ))}
          </div>
          {hasNextPage && (
            <div className="text-center mt-8">
              <button
                onClick={() => fetchNextPage()}
                disabled={isFetchingNextPage}
                className="px-4 py-2 bg-primary text-primary-foreground rounded-md disabled:opacity-50"
              >
                {isFetchingNextPage ? 'Loading more...' : 'Load More'}
              </button>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
