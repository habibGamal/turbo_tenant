import React, { createContext, useContext } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { emitCommerceEvent } from '@/core/analytics';

export interface User {
  id: number;
  name: string;
  email: string;
  phone?: string | null;
}

interface AuthContextType {
  user: User | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  login: (credentials: Record<string, any>) => Promise<any>;
  register: (data: Record<string, any>) => Promise<any>;
  logout: () => Promise<void>;
  refetchUser: () => void;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const queryClient = useQueryClient();

  const { data, isLoading, refetch } = useQuery<{ user: User | null }>({
    queryKey: ['auth', 'user'],
    queryFn: async () => {
      try {
        const res = await apiClient.get('/api/tenant/auth/user');
        return res.data;
      } catch (err: any) {
        if (err.response?.status === 401) {
          return { user: null };
        }
        throw err;
      }
    },
    staleTime: 1000 * 60 * 5,
  });

  const user = data?.user || null;

  const loginMutation = useMutation({
    mutationFn: async (credentials: Record<string, any>) => {
      const res = await apiClient.post('/api/tenant/auth/login', credentials);
      return res.data;
    },
    onSuccess: (data) => {
      queryClient.setQueryData(['auth', 'user'], { user: data.user });
      queryClient.invalidateQueries({ queryKey: ['tenant', 'bootstrap'] });
      queryClient.invalidateQueries({ queryKey: ['cart'] });
    },
  });

  const registerMutation = useMutation({
    mutationFn: async (payload: Record<string, any>) => {
      const res = await apiClient.post('/api/tenant/auth/register', payload);
      return res.data;
    },
    onSuccess: (data) => {
      queryClient.setQueryData(['auth', 'user'], { user: data.user });
      queryClient.invalidateQueries({ queryKey: ['tenant', 'bootstrap'] });
      queryClient.invalidateQueries({ queryKey: ['cart'] });
      emitCommerceEvent('CompleteRegistration', { status: true });
    },
  });

  const logoutMutation = useMutation({
    mutationFn: async () => {
      await apiClient.post('/api/tenant/auth/logout');
    },
    onSuccess: () => {
      queryClient.setQueryData(['auth', 'user'], { user: null });
      queryClient.invalidateQueries({ queryKey: ['tenant', 'bootstrap'] });
      queryClient.invalidateQueries({ queryKey: ['cart'] });
    },
  });

  return (
    <AuthContext.Provider
      value={{
        user,
        isAuthenticated: !!user,
        isLoading,
        login: loginMutation.mutateAsync,
        register: registerMutation.mutateAsync,
        logout: logoutMutation.mutateAsync,
        refetchUser: refetch,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthContextType {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}
