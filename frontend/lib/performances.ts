import { api, ensureCsrfCookie } from './api';

export type SaleStatus = 'upcoming' | 'open' | 'closed';
export type QueueEntryStatus = 'waiting' | 'admitted';

export type ShowSummary = {
  id: number;
  title: string;
  description: string;
  venue_label: string;
};

export type Performance = {
  id: number;
  price: number;
  starts_at: string;
  sale_opens_at: string;
  sale_closes_at: string;
  sale_status: SaleStatus;
  capacity: number;
  remaining_seats: number;
  show: ShowSummary;
};

export type QueueEntry = {
  id: number;
  status: QueueEntryStatus;
  position: number;
  joined_at: string;
};

export type PurchaseSlot = {
  id: number;
  assigned_at: string;
};

export type QueueAdmission = {
  performance: Performance;
  queue_entry: QueueEntry | null;
  purchase_slot: PurchaseSlot | null;
};

export type SeatsUpdatedPayload = {
  performance_id: number;
  capacity: number;
  remaining_seats: number;
};

export type SlotAssignedPayload = {
  purchase_slot: {
    id: number;
    performance_id: number;
    assigned_at: string;
  };
  queue_entry: {
    id: number;
    position: number;
    status: QueueEntryStatus;
  };
};

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
