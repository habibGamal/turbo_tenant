import React, { useState } from 'react';
import { useSubmitReview } from '../api/useSubmitReview';

interface DefaultReviewFormProps {
  productId: string | number;
  onSuccess?: () => void;
}

export default function DefaultReviewForm({ productId, onSuccess }: DefaultReviewFormProps) {
  const [rating, setRating] = useState(5);
  const [comment, setComment] = useState('');
  const [guestName, setGuestName] = useState('');
  const [guestEmail, setGuestEmail] = useState('');
  const [error, setError] = useState<string | null>(null);

  const { mutate, isPending } = useSubmitReview(productId);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!comment.trim()) {
      setError('Comment is required');
      return;
    }
    setError(null);

    mutate(
      {
        rating,
        comment,
        guest_name: guestName || undefined,
        guest_email: guestEmail || undefined,
      },
      {
        onSuccess: () => {
          setComment('');
          setGuestName('');
          setGuestEmail('');
          if (onSuccess) onSuccess();
        },
        onError: (err: any) => {
          setError(err?.response?.data?.message || 'Failed to submit review');
        },
      }
    );
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4 p-4 border rounded-lg bg-card text-card-foreground">
      <h4 className="font-semibold text-base">Write a Review</h4>
      {error && <p className="text-sm text-destructive">{error}</p>}

      <div>
        <label className="block text-sm font-medium mb-1">Rating</label>
        <select
          value={rating}
          onChange={(e) => setRating(Number(e.target.value))}
          className="w-full border rounded-md p-2 bg-background"
        >
          <option value={5}>⭐⭐⭐⭐⭐ (5 Stars)</option>
          <option value={4}>⭐⭐⭐⭐ (4 Stars)</option>
          <option value={3}>⭐⭐⭐ (3 Stars)</option>
          <option value={2}>⭐⭐ (2 Stars)</option>
          <option value={1}>⭐ (1 Star)</option>
        </select>
      </div>

      <div>
        <label className="block text-sm font-medium mb-1">Your Name</label>
        <input
          type="text"
          value={guestName}
          onChange={(e) => setGuestName(e.target.value)}
          placeholder="Optional if signed in"
          className="w-full border rounded-md p-2 bg-background"
        />
      </div>

      <div>
        <label className="block text-sm font-medium mb-1">Your Review</label>
        <textarea
          value={comment}
          onChange={(e) => setComment(e.target.value)}
          required
          rows={3}
          placeholder="Share your experience..."
          className="w-full border rounded-md p-2 bg-background"
        />
      </div>

      <button
        type="submit"
        disabled={isPending}
        className="w-full py-2 px-4 bg-primary text-primary-foreground font-medium rounded-md disabled:opacity-50"
      >
        {isPending ? 'Submitting...' : 'Submit Review'}
      </button>
    </form>
  );
}
