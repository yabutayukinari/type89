'use client';

import { useRouter, useSearchParams } from 'next/navigation';
import { Suspense } from 'react';
import LoginForm from '@/components/LoginForm';
import { loginUser } from '@/lib/auth';
import { safeNextPath } from '@/lib/apiError';

export default function UserLoginPage() {
  return (
    <Suspense
      fallback={
        <main className="grid min-h-screen place-items-center text-sm text-zinc-400">読み込み中...</main>
      }
    >
      <UserLoginForm />
    </Suspense>
  );
}

function UserLoginForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const next = safeNextPath(searchParams.get('next'));

  const handleSubmit = async (email: string, password: string) => {
    await loginUser(email, password);
    router.push(next);
  };

  return (
    <LoginForm
      title="ログイン"
      onSubmit={handleSubmit}
      demoHint="デモ: test_user@example.com / test1111"
    />
  );
}
