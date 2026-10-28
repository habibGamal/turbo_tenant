import React from 'react';
import { usePackages } from '../api/usePackages';
import DefaultPackageCard from './DefaultPackageCard';

export default function DefaultPackagesSection() {
  const { data: packages, isLoading } = usePackages();

  if (isLoading || !packages || packages.length === 0) {
    return null;
  }

  return (
    <section className="py-8 my-6">
      <div className="container mx-auto px-4">
        <h2 className="text-2xl font-bold mb-6">Combo Packages & Meals</h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          {packages.map((pkg) => (
            <DefaultPackageCard key={pkg.id} packageItem={pkg} />
          ))}
        </div>
      </div>
    </section>
  );
}
