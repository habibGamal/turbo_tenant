import React from 'react';
import { ComboPackage } from '../types';

interface DefaultPackageCardProps {
  packageItem: ComboPackage;
}

export default function DefaultPackageCard({ packageItem }: DefaultPackageCardProps) {
  return (
    <div className="border rounded-xl p-4 bg-card text-card-foreground shadow-sm flex flex-col justify-between">
      {packageItem.image && (
        <img
          src={packageItem.image}
          alt={packageItem.name}
          className="w-full h-44 object-cover rounded-lg mb-3"
        />
      )}
      <div>
        <h4 className="font-bold text-base mb-1">{packageItem.name}</h4>
        {packageItem.description && (
          <p className="text-xs text-muted-foreground line-clamp-2 mb-3">
            {packageItem.description}
          </p>
        )}
      </div>
      <div className="flex items-center justify-between mt-3 pt-3 border-t">
        <span className="font-bold text-primary">{packageItem.price} EGP</span>
        <button className="px-3 py-1.5 text-xs font-medium bg-primary text-primary-foreground rounded-md">
          Choose Package
        </button>
      </div>
    </div>
  );
}
