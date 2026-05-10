import axios, { AxiosInstance } from 'axios';

const baseURL = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost';

export const api: AxiosInstance = axios.create({
  baseURL,
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
});

let csrfPromise: Promise<void> | null = null;

export const ensureCsrfCookie = (): Promise<void> => {
  if (!csrfPromise) {
    csrfPromise = api.get('/sanctum/csrf-cookie').then(() => undefined);
  }
  return csrfPromise;
};
