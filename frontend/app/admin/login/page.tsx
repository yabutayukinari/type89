'use client';

import { useRouter } from 'next/navigation';
import LoginForm from '@/components/LoginForm';
import { loginAdmin } from '@/lib/auth';

export default function AdminLoginPage() {
  const router = useRouter();

  const handleSubmit = async (email: string, password: string) => {
    await loginAdmin(email, password);
    router.push('/admin/me');
  };

  return <LoginForm title="Admin ログイン" onSubmit={handleSubmit} />;
}
