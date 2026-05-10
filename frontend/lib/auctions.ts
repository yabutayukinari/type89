import { api, ensureCsrfCookie } from './api';

export type AuctionStatus = 'pending' | 'active' | 'ended';

export type AuctionUser = {
  id: number;
  name: string;
};

export type Auction = {
  id: number;
  title: string;
  description: string;
  seller: AuctionUser;
  starting_price: number;
  bid_increment: number;
  current_price: number;
  min_next_bid: number;
  current_winner: AuctionUser | null;
  starts_at: string;
  ends_at: string;
  status: AuctionStatus;
};

export type Bid = {
  id: number;
  auction_id: number;
  amount: number;
  bidder: AuctionUser;
  created_at: string;
};

export type BidPlacedPayload = {
  bid: Bid;
  auction: {
    id: number;
    current_price: number;
    min_next_bid: number;
    current_winner: AuctionUser;
  };
};

export const fetchAuctions = async (): Promise<Auction[]> => {
  const response = await api.get<{ data: Auction[] }>('/api/auctions');
  return response.data.data;
};

export const fetchAuction = async (id: number): Promise<Auction> => {
  const response = await api.get<{ data: Auction }>(`/api/auctions/${id}`);
  return response.data.data;
};

export type CreateAuctionInput = {
  title: string;
  description: string;
  starting_price: number;
  bid_increment: number;
  starts_at: string;
  ends_at: string;
};

export const createAuction = async (input: CreateAuctionInput): Promise<Auction> => {
  await ensureCsrfCookie();
  const response = await api.post<{ data: Auction }>('/api/auctions', input);
  return response.data.data;
};

export const placeBid = async (auctionId: number, amount: number): Promise<Bid> => {
  await ensureCsrfCookie();
  const response = await api.post<{ data: Bid }>(`/api/auctions/${auctionId}/bids`, { amount });
  return response.data.data;
};
