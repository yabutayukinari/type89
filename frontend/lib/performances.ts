import { api, ensureCsrfCookie } from './api';
import type { QueueAdmission, Performance } from './ticketQueueTrust';

export type {
  EchoConnectionState,
  Performance,
  PurchaseSlot,
  PurchaseSlotStatus,
  QueueAdmission,
  QueueEntry,
  QueueEntryStatus,
  QueueNotice,
  QueueWaitReason,
  SaleStatus,
  SeatsUpdatedPayload,
  SlotAssignedPayload,
  SlotRelease,
  SlotReleaseReason,
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

export const confirmHold = async (performanceId: number): Promise<QueueAdmission> => {
  await ensureCsrfCookie();
  const response = await api.post<{ data: QueueAdmission }>(`/api/performances/${performanceId}/queue/confirm`);
  return response.data.data;
};

export const cancelHold = async (performanceId: number): Promise<QueueAdmission> => {
  await ensureCsrfCookie();
  const response = await api.post<{ data: QueueAdmission }>(`/api/performances/${performanceId}/queue/cancel`);
  return response.data.data;
};

export type OrganizerSlot = {
  id: number;
  status: 'held' | 'confirmed';
  assigned_at: string;
  expires_at: string | null;
  confirmed_at: string | null;
  user: { id: number; name: string; email: string };
};

export type OrganizerEvent = {
  id: number;
  type: 'held' | 'confirmed' | 'released';
  release_reason: 'self_cancel' | 'ttl' | null;
  remaining_seats_after: number;
  occurred_at: string;
  user: { id: number; name: string; email: string };
};

export type OrganizerInventory = {
  performance: Performance;
  held_count: number;
  confirmed_count: number;
  waiting_count: number;
  remaining_seats: number;
  capacity: number;
  hold_ttl_seconds: number;
  current_slots: OrganizerSlot[];
  events: OrganizerEvent[];
};

export const fetchAdminPerformances = async (): Promise<Performance[]> => {
  const response = await api.get<{ data: Performance[] }>('/api/admin/performances');
  return response.data.data;
};

export const fetchAdminInventory = async (performanceId: number): Promise<OrganizerInventory> => {
  const response = await api.get<{ data: OrganizerInventory }>(`/api/admin/performances/${performanceId}`);
  return response.data.data;
};
