export interface ImportJobError {
  row: number;
  externalCode: string | null;
  messages: string[];
}

export type ImportJobStatus = 'pending' | 'processing' | 'completed' | 'failed';

export interface ImportJob {
  id: number;
  status: ImportJobStatus;
  total: number;
  processed: number;
  errors: ImportJobError[];
  createdAt: string;
  updatedAt: string;
}

export interface ImportStartResponse {
  jobId: number;
  status: ImportJobStatus;
  statusUrl: string;
}
