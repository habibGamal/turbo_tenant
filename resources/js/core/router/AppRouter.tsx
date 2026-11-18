import React, { Suspense } from 'react';
import { BrowserRouter, Routes, Route, Navigate, useParams } from 'react-router-dom';
import { PluginSlot } from '@/core/slots/PluginSlot';
import { RequirePlugin } from '@/core/router/RequirePlugin';
import { CheckoutGuard } from '@/core/router/guards/CheckoutGuard';
import { ThemedComponent } from '@/core/router/ThemedComponent';
import { useActiveTheme } from '@/core/hooks/useActiveTheme';

function CartRoute() {
  const { layouts: { MainLayout } } = useActiveTheme();
  return (
    <MainLayout>
      <PluginSlot
        name="cart.page"
        pluginId="cart-engine"
        fallback={<ThemedComponent name="ThemedCart" />}
      />
    </MainLayout>
  );
}

function CmsPageRoute() {
  const { slug } = useParams<{ slug: string }>();
  const { layouts: { MainLayout } } = useActiveTheme();
  return (
    <MainLayout>
      <PluginSlot
        name="cms.page"
        pluginId="cms-pages"
        context={{ slug }}
        fallback={<ThemedComponent name="ThemedCmsPages" props={{ slug }} />}
      />
    </MainLayout>
  );
}

function FavoritesRoute() {
  const { layouts: { MainLayout } } = useActiveTheme();
  return (
    <MainLayout>
      <PluginSlot
        name="favorites.page"
        pluginId="customer-favorites"
        fallback={<ThemedComponent name="ThemedCustomerFavorites" />}
      />
    </MainLayout>
  );
}

function HomeRoute() {
  const { layouts: { MainLayout } } = useActiveTheme();
  return (
    <MainLayout>
      <div className="min-h-screen">
        <PluginSlot name="home.hero" pluginId="hero-banners" />
        <PluginSlot name="home.sections.combos" pluginId="combo-packages" />
        <PluginSlot name="home.sections.after" pluginId="catalog-products" />
      </div>
    </MainLayout>
  );
}

function MenuRoute() {
  return (
    <PluginSlot
      name="menu.page"
      pluginId="catalog-products"
      fallback={<ThemedComponent name="ThemedMenuPage" />}
    />
  );
}

function ProductDetailsRoute() {
  const { id } = useParams<{ id: string }>();
  return (
    <PluginSlot
      name="product.page"
      pluginId="catalog-products"
      context={{ productId: id }}
      fallback={<ThemedComponent name="ThemedProductShow" props={{ productId: id }} />}
    />
  );
}

function PackageDetailsRoute() {
  const { id } = useParams<{ id: string }>();
  const { layouts: { MainLayout } } = useActiveTheme();
  return (
    <MainLayout>
      <PluginSlot
        name="package.page"
        pluginId="combo-packages"
        context={{ packageId: id }}
      />
    </MainLayout>
  );
}

function OrdersRoute() {
  const { layouts: { MainLayout } } = useActiveTheme();
  return (
    <MainLayout>
      <ThemedComponent name="ThemedOrdersList" />
    </MainLayout>
  );
}

function TrackOrderRoute() {
  const { layouts: { MainLayout } } = useActiveTheme();
  return (
    <MainLayout>
      <ThemedComponent name="ThemedGuestOrderTracking" />
    </MainLayout>
  );
}

function OrderDetailsRoute() {
  const { id } = useParams<{ id: string }>();
  const { pages: { OrderShow } } = useActiveTheme();
  return <OrderShow orderId={id} />;
}

function RouteLoadingFallback() {
  const { layouts: { MainLayout } } = useActiveTheme();
  return (
    <MainLayout>
      <div role="status" className="min-h-[60vh] p-8" aria-label="Loading page" />
    </MainLayout>
  );
}

export function AppRouter() {
  const { pages } = useActiveTheme();
  const { Checkout, Login, Register, ForgotPassword, ResetPassword, Profile, NotFound } = pages;

  return (
    <BrowserRouter>
      <PluginSlot name="router.tracking" />
      <Suspense fallback={<RouteLoadingFallback />} >
      <Routes>
        <Route path="/" element={<HomeRoute />} />
        <Route
          path="/menu"
          element={
            <RequirePlugin id="catalog-products">
              <MenuRoute />
            </RequirePlugin>
          }
        />
        <Route
          path="/products/:id"
          element={
            <RequirePlugin id="catalog-products">
              <ProductDetailsRoute />
            </RequirePlugin>
          }
        />
        <Route
          path="/packages/:id"
          element={
            <RequirePlugin id="combo-packages">
              <PackageDetailsRoute />
            </RequirePlugin>
          }
        />
        <Route
          path="/pages/:slug"
          element={
            <RequirePlugin id="cms-pages">
              <CmsPageRoute />
            </RequirePlugin>
          }
        />
        <Route
          path="/cart"
          element={
            <RequirePlugin id="cart-engine">
              <CartRoute />
            </RequirePlugin>
          }
        />
        <Route
          path="/checkout"
          element={
            <CheckoutGuard>
              <Checkout />
            </CheckoutGuard>
          }
        />
        <Route
          path="/orders"
          element={
            <RequirePlugin id="order-management">
              <OrdersRoute />
            </RequirePlugin>
          }
        />
        <Route
          path="/orders/:id/payment/callback"
          element={
            <RequirePlugin id="payment-processing">
              <ThemedComponent name="ThemedPaymentCallback" />
            </RequirePlugin>
          }
        />
        <Route
          path="/payments/:id/kashier"
          element={
            <RequirePlugin id="payment-processing">
              <ThemedComponent name="ThemedKashierPayment" />
            </RequirePlugin>
          }
        />
        <Route
          path="/orders/track"
          element={
            <RequirePlugin id="guest-checkout">
              <TrackOrderRoute />
            </RequirePlugin>
          }
        />
        <Route
          path="/track-order"
          element={
            <RequirePlugin id="guest-checkout">
              <TrackOrderRoute />
            </RequirePlugin>
          }
        />
        <Route
          path="/orders/:id"
          element={
            <RequirePlugin id="order-management">
              <OrderDetailsRoute />
            </RequirePlugin>
          }
        />
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<Register />} />
        <Route path="/forgot-password" element={<ForgotPassword />} />
        <Route path="/reset-password/:token" element={<ResetPassword />} />
        <Route path="/profile" element={<Profile />} />
        <Route
          path="/favorites"
          element={
            <RequirePlugin id="customer-favorites">
              <FavoritesRoute />
            </RequirePlugin>
          }
        />
        <Route path="*" element={NotFound ? <NotFound /> : <Navigate to="/" replace />} />
      </Routes>
      </Suspense>
    </BrowserRouter>
  );
}
