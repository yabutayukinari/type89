import { api, ensureCsrfCookie } from './api';
import type { QueueAdmission, Performance } from './ticketQueueTrust';

export type {
  EchoConnectionState,
  Performance,
  PurchaseSlot,
  QueueAdmission,
  QueueEntry,
  QueueEntryStatus,
  QueueNotice,
  QueueWaitReason,
  SaleStatus,
  SeatsUpdatedPayload,
  SlotAssignedPayload,
} from './ticketQueueTrust';

export const fetchPerformances = async (): Promise<Performance[]> => {
  const response = await api.get<{ data: Performance[] }>('/api/performances');
  return response.data.data;
};

export const fetchPerformance = async (id: number): Promise<Performance> => {
  const response = await api.get<{ data: Performance }>(`/api/performances/${id}`);
  return response.data.data;
};

export const fetchQueueStatus = async (performanceId: number): Promise<QueueAdmission> => {
  const response = await api.get<{ data: QueueAdmission }>(`/api/performances/${performanceId}/queue`);
  return response.data.data;
};

export const joinQueue = async (performanceId: number): Promise<QueueAdmission> => {
  await ensureCsrfCookie();
  const response = await api.post<{ data: QueueAdmission }>(`/api/performances/${performanceId}/queue`);
  return response.data.data;
};
