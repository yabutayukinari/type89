'use client';

import { useCallback, useEffect, useState } from 'react';
import { api, ensureCsrfCookie } from './api';

export type User = {
  id: number;
  name: string;
  email: string;
};

export type AdminRole = 'system_admin' | 'general_admin';

export type Admin = {
  id: number;
  name: string;
  email: string;
  role: AdminRole;
};

export type AuthState<T> =
  | { state: 'loading' }
  | { state: 'authenticated'; principal: T }
  | { state: 'unauthenticated' };

const useAuthFor = <T>(meEndpoint: string): {
  auth: AuthState<T>;
  refresh: () => Promise<void>;
} => {
  const [auth, setAuth] = useState<AuthState<T>>({ state: 'loading' });

  const fetchPrincipal = useCallback(async (): Promise<AuthState<T>> => {
    try {
      const response = await api.get<{ data: T }>(meEndpoint);
      return { state: 'authenticated', principal: response.data.data };
    } catch {
      return { state: 'unauthenticated' };
    }
  }, [meEndpoint]);

  const refresh = useCallback(async (): Promise<void> => {
    setAuth(await fetchPrincipal());
  }, [fetchPrincipal]);

  useEffect(() => {
    let cancelled = false;
    fetchPrincipal().then((next) => {
      if (!cancelled) {
        setAuth(next);
      }
    });
    return () => {
      cancelled = true;
    };
  }, [fetchPrincipal]);

  return { auth, refresh };
};

export const useUser = () => useAuthFor<User>('/api/me');
export const useAdmin = () => useAuthFor<Admin>('/api/admin/me');

export const loginUser = async (email: string, password: string): Promise<User> => {
  await ensureCsrfCookie();
  const response = await api.post<{ data: User }>('/api/login', { email, password });
  return response.data.data;
};

export const logoutUser = async (): Promise<void> => {
  await api.post('/api/logout');
};

export const loginAdmin = async (email: string, password: string): Promise<Admin> => {
  await ensureCsrfCookie();
  const response = await api.post<{ data: Admin }>('/api/admin/login', { email, password });
  return response.data.data;
};

export const logoutAdmin = async (): Promise<void> => {
  await api.post('/api/admin/logout');
};
