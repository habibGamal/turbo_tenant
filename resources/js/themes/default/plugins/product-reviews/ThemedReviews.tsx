import React from 'react';
import { useProductReviews } from '@/plugins/product-reviews/api/useProductReviews';
import ReviewsSection from './components/ReviewsSection';
import { ReviewsSkeleton } from '@/components/ui/page-skeletons';

interface ThemedReviewsProps {
  productId?: number | string;
  context?: {
    productId?: number | string;
  };
}

export default function ThemedReviews(props: ThemedReviewsProps) {
  const productId = props.productId || props.context?.productId;
  const numId = Number(productId);

  const { data, isLoading } = useProductReviews(numId);

  if (!numId) return null;

  const reviews = data?.data || [];
  const averageRating = reviews.length > 0
    ? reviews.reduce((acc, r) => acc + r.rating, 0) / reviews.length
    : 5;

  if (isLoading) {
    return <ReviewsSkeleton />;
  }

  return (
    <ReviewsSection
      productId={numId}
      reviews={reviews}
      averageRating={averageRating}
    />
  );
}
