import { api } from "./client";

/**
 * Public metadata returned for an authorized delivery proof.
 * Private storage paths must never be exposed.
 */
export interface DeliveryDocument {
  id: number;
  mission_id: number;
  mime_type: string;
  uploaded_at: string;
}

interface DocumentListResponse {
  data: DeliveryDocument[];
}

/**
 * Upload a delivery proof for an assigned, in-progress mission.
 *
 * FormData allows the browser to construct the multipart
 * request, including the correct boundary.
 */
export async function uploadDeliveryDocument(
  missionId: number,
  file: File,
): Promise<DeliveryDocument> {
  const formData = new FormData();
  formData.append("file", file);

  const response = await api.post<DeliveryDocument>(
    `/api/missions/${missionId}/documents`,
    formData,
  );

  return response.data;
}

/**
 * Retrieve the documents available to the owning dispatcher.
 *
 * The backend filters access and never returns file paths.
 */
export async function getMissionDocuments(
  missionId: number,
): Promise<DeliveryDocument[]> {
  const response = await api.get<DocumentListResponse>(
    `/api/missions/${missionId}/documents`,
  );

  return response.data.data;
}

/**
 * Download a delivery proof through the authenticated API.
 *
 * The extension is derived from the response MIME type,
 * while the backend independently controls the download name.
 */
export async function downloadDeliveryDocument(
  missionId: number,
  documentId: number,
): Promise<void> {
  const response = await api.get<Blob>(
    `/api/missions/${missionId}/documents/${documentId}`,
    {
      responseType: "blob",
    },
  );

  // Read the server-provided download filename when available.
  // Axios exposes accessible Content-Disposition headers.
  const disposition = response.headers["content-disposition"] as
    | string
    | undefined;

  const filenameMatch = disposition?.match(/filename="?([^";]+)"?/i);

  const filename = filenameMatch?.[1] ?? `delivery-proof-${documentId}`;

  // Server-generated filenames are plain names, not paths.
  const safeFilename = filename.replace(/[/\\]/g, "_").replace(/[\r\n]/g, "");

  const downloadUrl = URL.createObjectURL(response.data);

  try {
    const link = document.createElement("a");
    link.href = downloadUrl;
    link.download = safeFilename;
    link.style.display = "none";

    document.body.appendChild(link);

    try {
      link.click();
    } finally {
      link.remove();
    }
  } finally {
    URL.revokeObjectURL(downloadUrl);
  }
}
