import { useState } from "react";
import { FileText } from "lucide-react";
import { useTranslation } from "react-i18next";
import { toast } from "react-toastify";
import { downloadApplicationAttachment } from "../../Api/applicationAttachmentsApi";
import Button from "./Button";

export default function ApplicationAttachmentButton({ applicationId, attachmentType, children }) {
  const [downloading, setDownloading] = useState(false);
  const { t } = useTranslation("common");

  async function download() {
    setDownloading(true);
    try {
      await downloadApplicationAttachment(applicationId, attachmentType);
    } catch (error) {
      if (error.response?.status !== 401) toast.error(t("attachmentDownloadFailed"));
    } finally {
      setDownloading(false);
    }
  }

  return (
    <Button size="sm" variant="soft" icon={FileText} disabled={!applicationId} loading={downloading} onClick={download}>
      {children}
    </Button>
  );
}
