import React from 'react';
import { BrowserRouter, Routes, Route, Navigate, useParams } from 'react-router-dom';
import { PluginSlot } from '@/core/slots/PluginSlot';
import { RequirePlugin } from '@/core/router/RequirePlugin';
import MainLayout from '@/themes/default/layouts/MainLayout';
import { ThemedCmsPages } from '@/themes/default/plugins/cms-pages';
import { ThemedCustomerFavorites } from '@/themes/default/plugins/customer-favorites';
import { ThemedCart } from '@/themes/default/plugins/cart-engine';
import { ThemedGuestOrderTracking } from '@/themes/default/plugins/guest-checkout';
import { ThemedOrdersList } from '@/themes/default/plugins/order-management';
import { ThemedPaymentCallback, ThemedKashierPayment } from '@/themes/default/plugins/payment-processing';
import { ThemedProductShow, ThemedMenuPage } from '@/themes/default/plugins/catalog-products';
import Checkout from '@/themes/default/pages/Checkout';
import OrderShow from '@/themes/default/pages/OrderShow';
import Login from '@/themes/default/pages/Login';
import Register from '@/themes/default/pages/Register';
import ForgotPassword from '@/themes/default/pages/ForgotPassword';
import ResetPassword from '@/themes/default/pages/ResetPassword';
import Profile from '@/themes/default/pages/Profile';

function CartRoute() {
  return (
    <MainLayout>
      <PluginSlot
        name="cart.page"
        pluginId="cart-engine"
        fallback={<ThemedCart />}
      />
    </MainLayout>
  );
}

function CmsPageRoute() {
  const { slug } = useParams<{ slug: string }>();
  return (
    <MainLayout>
      <PluginSlot
        name="cms.page"
        pluginId="cms-pages"
        context={{ slug }}
        fallback={<ThemedCmsPages slug={slug} />}
      />
    </MainLayout>
  );
}

function FavoritesRoute() {
  return (
    <MainLayout>
      <PluginSlot
        name="favorites.page"
        pluginId="customer-favorites"
        fallback={<ThemedCustomerFavorites />}
      />
    </MainLayout>
  );
}

function HomeRoute() {
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
      fallback={<ThemedMenuPage />}
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
      fallback={<ThemedProductShow productId={id} />}
    />
  );
}

function OrdersRoute() {
  return (
    <MainLayout>
      <ThemedOrdersList />
    </MainLayout>
  );
}

function TrackOrderRoute() {
  return (
    <MainLayout>
      <ThemedGuestOrderTracking />
    </MainLayout>
  );
}

function OrderDetailsRoute() {
  const { id } = useParams<{ id: string }>();
  return <OrderShow orderId={id} />;
}

export function AppRouter() {
  return (
    <BrowserRouter>
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
        <Route path="/checkout" element={<Checkout />} />
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
              <ThemedPaymentCallback />
            </RequirePlugin>
          }
        />
        <Route
          path="/payments/:id/kashier"
          element={
            <RequirePlugin id="payment-processing">
              <ThemedKashierPayment />
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
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </BrowserRouter>
  );
}
