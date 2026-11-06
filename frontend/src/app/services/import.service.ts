import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { ImportJob, ImportStartResponse } from '../models/import.model';

@Injectable({ providedIn: 'root' })
export class ImportService {
  constructor(private readonly http: HttpClient) {}

  upload(file: File): Observable<ImportStartResponse> {
    const formData = new FormData();
    formData.append('file', file, file.name);

    return this.http.post<ImportStartResponse>('/api/import', formData);
  }

  getStatus(jobId: number): Observable<ImportJob> {
    return this.http
      .get<{ data: ImportJob }>(`/api/import/${jobId}`)
      .pipe(map((response) => response.data));
  }
}
