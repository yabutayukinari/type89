'use client';

import { useRouter } from 'next/navigation';
import LoginForm from '@/components/LoginForm';
import { loginUser } from '@/lib/auth';

export default function UserLoginPage() {
  const router = useRouter();

  const handleSubmit = async (email: string, password: string) => {
    await loginUser(email, password);
    router.push('/me');
  };

  return (
    <LoginForm
      title="User ログイン"
      onSubmit={handleSubmit}
      demoHint="デモ: test_user@example.com / test1111"
    />
  );
}
