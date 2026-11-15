import React, { useState } from 'react';
import { useProductReviews } from '../api/useProductReviews';
import DefaultReviewForm from './DefaultReviewForm';

interface DefaultReviewsSectionProps {
  productId: string | number;
}

export default function DefaultReviewsSection({ productId }: DefaultReviewsSectionProps) {
  const [showForm, setShowForm] = useState(false);
  const { data: reviews = [], isLoading } = useProductReviews(productId);

  return (
    <div className="space-y-6 my-8 p-4 border rounded-xl bg-card text-card-foreground">
      <div className="flex items-center justify-between">
        <h3 className="text-xl font-bold">Customer Reviews ({reviews.length})</h3>
        <button
          onClick={() => setShowForm(!showForm)}
          className="text-sm px-4 py-2 bg-primary text-primary-foreground rounded-md font-medium"
        >
          {showForm ? 'Cancel' : 'Write Review'}
        </button>
      </div>

      {showForm && (
        <DefaultReviewForm productId={productId} onSuccess={() => setShowForm(false)} />
      )}

      {isLoading ? (
        <p className="text-sm text-muted-foreground">Loading reviews...</p>
      ) : reviews.length === 0 ? (
        <p className="text-sm text-muted-foreground">No reviews yet. Be the first to review!</p>
      ) : (
        <div className="divide-y">
          {reviews.map((rev) => (
            <div key={rev.id} className="py-4">
              <div className="flex items-center justify-between mb-1">
                <span className="font-semibold text-sm">
                  {rev.user?.name || rev.guest_name || 'Anonymous'}
                </span>
                <span className="text-yellow-500 text-sm">
                  {'★'.repeat(rev.rating)}{'☆'.repeat(5 - rev.rating)}
                </span>
              </div>
              <p className="text-sm text-muted-foreground">{rev.comment}</p>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
