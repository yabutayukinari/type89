export const safeNextPath = (value: string | null, fallback = '/me'): string => {
  if (value === null || value === '') {
    return fallback;
  }
  if (!value.startsWith('/') || value.startsWith('//') || value.startsWith('/\\')) {
    return fallback;
  }
  return value;
};

export const extractApiMessage = (err: { response?: unknown }, fallback: string): string => {
  const response = err.response;
  if (
    response &&
    typeof response === 'object' &&
    'data' in response &&
    response.data &&
    typeof response.data === 'object' &&
    'message' in response.data &&
    typeof response.data.message === 'string'
  ) {
    return response.data.message;
  }
  return fallback;
};
