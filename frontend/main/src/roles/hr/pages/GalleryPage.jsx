import { useEffect, useRef, useState } from "react";
import { ExternalLink, Images, Pencil, Plus, Trash2, X } from "lucide-react";
import { useTranslation } from "react-i18next";
import { toast } from "react-toastify";
import Button from "../../../components/ui/Button";
import { Field, Input, Textarea } from "../../../components/ui/Field";
import Loading from "../../../components/ui/Loading";
import {
  deleteGalleryPhoto,
  galleryImageUrl,
  getGalleryPage,
  saveGalleryPhoto,
} from "../../../Api/galleryApi";
import styles from "./GalleryPage.module.css";

function errorMessage(error, fallback) {
  const data = error.response?.data;
  return data?.errors ? Object.values(data.errors).flat().join(" ") : fallback;
}

function GalleryDialog({ title, children, busy, onClose }) {
  const { t } = useTranslation("gallery");
  const dialog = useRef(null);

  useEffect(() => {
    const element = dialog.current;
    if (!element.open) element.showModal();
  }, []);

  return (
    <dialog
      ref={dialog}
      className={styles.dialog}
      aria-labelledby="gallery-dialog-title"
      onClose={onClose}
      onCancel={event => { if (busy) event.preventDefault(); }}
    >
      <header className={styles.dialogHeading}>
        <h2 id="gallery-dialog-title">{title}</h2>
        <Button variant="secondary" size="sm" icon={X} aria-label={t("close")} disabled={busy}
          onClick={() => dialog.current.close()} />
      </header>
      <div className={styles.dialogBody}>{children}</div>
    </dialog>
  );
}

function PhotoEditor({ photo, onClose, onSaved }) {
  const { t } = useTranslation("gallery");
  const [title, setTitle] = useState(photo?.title || "");
  const [description, setDescription] = useState(photo?.description || "");
  const [image, setImage] = useState(null);
  const [preview, setPreview] = useState("");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (!image) { setPreview(""); return; }
    const url = URL.createObjectURL(image);
    setPreview(url);
    return () => URL.revokeObjectURL(url);
  }, [image]);

  async function handleSubmit(event) {
    event.preventDefault();
    if (busy || !title.trim() || (!photo && !image)) return;
    setBusy(true);
    setError("");
    try {
      await saveGalleryPhoto(photo?.id, { title, description, image });
      onSaved();
    } catch (requestError) {
      setError(errorMessage(requestError, t("saveError")));
      setBusy(false);
    }
  }

  const previewUrl = preview || (photo && galleryImageUrl(photo));
  return (
    <GalleryDialog title={t(photo ? "edit" : "add")} busy={busy} onClose={onClose}>
      <form className={styles.form} onSubmit={handleSubmit}>
        {previewUrl && <img className={styles.preview} src={previewUrl} alt={t("preview")} />}
        <Field label={t(photo ? "replaceImage" : "image")}>
          <Input
            type="file"
            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
            required={!photo}
            disabled={busy}
            onChange={event => {
              const selected = event.target.files[0] || null;
              setError("");
              if (selected && (selected.size > 8 * 1024 * 1024 || !/\.(jpe?g|png|webp)$/i.test(selected.name))) {
                event.target.value = "";
                setImage(null);
                setError(t("invalidImage"));
                return;
              }
              setImage(selected);
            }}
          />
        </Field>
        <p className={styles.muted}>{t("fileHint")}</p>
        <Field label={t("photoTitle")}>
          <Input value={title} onChange={event => setTitle(event.target.value)}
            maxLength={120} dir="auto" required disabled={busy} />
        </Field>
        <Field label={t("caption")}>
          <Textarea value={description} onChange={event => setDescription(event.target.value)}
            maxLength={1000} dir="auto" disabled={busy} />
        </Field>
        {error && <p className={styles.error} role="alert">{error}</p>}
        <div className={styles.formActions}>
          <Button variant="secondary" onClick={onClose} disabled={busy}>{t("cancel")}</Button>
          <Button type="submit" loading={busy} disabled={!title.trim() || (!photo && !image)}>
            {t(photo ? "save" : "publish")}
          </Button>
        </div>
      </form>
    </GalleryDialog>
  );
}

function DeletePhoto({ photo, onClose, onDeleted }) {
  const { t } = useTranslation("gallery");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  async function remove() {
    if (busy) return;
    setBusy(true);
    setError("");
    try {
      await deleteGalleryPhoto(photo.id);
      onDeleted();
    } catch (requestError) {
      setError(errorMessage(requestError, t("deleteError")));
      setBusy(false);
    }
  }

  return (
    <GalleryDialog title={t("delete")} busy={busy} onClose={onClose}>
      <p>{t("confirmDelete", { title: photo.title })}</p>
      {error && <p className={styles.error} role="alert">{error}</p>}
      <div className={styles.formActions}>
        <Button variant="secondary" onClick={onClose} disabled={busy}>{t("cancel")}</Button>
        <Button icon={Trash2} onClick={remove} loading={busy}>{t("delete")}</Button>
      </div>
    </GalleryDialog>
  );
}

const guestUrl = import.meta.env.VITE_GUEST_URL;

export default function GalleryPage() {
  const { t } = useTranslation("gallery");
  const [result, setResult] = useState(null);
  const [page, setPage] = useState(1);
  const [version, setVersion] = useState(0);
  const [dialog, setDialog] = useState(null);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    const controller = new AbortController();
    setFailed(false);
    getGalleryPage(page, controller.signal)
      .then(data => {
        if (controller.signal.aborted) return;
        if (page > data.last_page) setPage(data.last_page);
        else setResult({ ...data, version });
      })
      .catch(() => { if (!controller.signal.aborted) setFailed(true); });
    return () => controller.abort();
  }, [page, version]);

  const loading = result?.current_page !== page || result?.version !== version;
  const reload = () => setVersion(value => value + 1);
  const close = () => setDialog(null);
  const photos = result?.data ?? [];

  return (
    <div className={styles.page}>
      <header className={styles.heading}>
        <div>
          <h1>{t("title")}</h1>
          <p>{t("intro")}</p>
        </div>
        <div className={styles.headerActions}>
          {guestUrl && (
            <a href={guestUrl.replace(/\/+$/, "") + "/gallery"} target="_blank" rel="noopener noreferrer">
              {t("openPublic")} <ExternalLink size={15} aria-hidden="true" />
            </a>
          )}
          <Button icon={Plus} onClick={() => setDialog({ action: "edit" })}>{t("add")}</Button>
        </div>
      </header>
      {failed ? (
        <div className={styles.empty} role="alert">
          <p>{t("loadError")}</p>
          <Button variant="secondary" onClick={reload}>{t("retry")}</Button>
        </div>
      ) : loading ? <Loading /> : photos.length === 0 ? (
        <div className={styles.empty}>
          <Images size={32} aria-hidden="true" />
          <h2>{t("empty")}</h2>
          <p>{t("emptyHint")}</p>
        </div>
      ) : (
        <>
          <ul className={styles.grid}>
            {photos.map(photo => (
              <li className={styles.card} key={photo.id}>
                <img src={galleryImageUrl(photo)} alt={photo.title} loading="lazy" decoding="async" />
                <div className={styles.details}>
                  <h2 dir="auto">{photo.title}</h2>
                  {photo.description && <p dir="auto">{photo.description}</p>}
                </div>
                <div className={styles.cardActions}>
                  <Button variant="secondary" size="sm" icon={Pencil} aria-label={t("editNamed", { title: photo.title })}
                    onClick={() => setDialog({ action: "edit", photo })}>{t("edit")}</Button>
                  <Button variant="soft" size="sm" icon={Trash2} aria-label={t("deleteNamed", { title: photo.title })}
                    onClick={() => setDialog({ action: "delete", photo })}>{t("delete")}</Button>
                </div>
              </li>
            ))}
          </ul>
          {result.last_page > 1 && (
            <nav className={styles.pagination} aria-label={t("pagination")}>
              <Button variant="secondary" disabled={page === 1} onClick={() => setPage(value => value - 1)}>{t("previous")}</Button>
              <span>{t("page", { current: page, total: result.last_page })}</span>
              <Button variant="secondary" disabled={page === result.last_page} onClick={() => setPage(value => value + 1)}>{t("next")}</Button>
            </nav>
          )}
        </>
      )}
      {dialog?.action === "edit" && (
        <PhotoEditor photo={dialog.photo} onClose={close} onSaved={() => {
          toast.success(t(dialog.photo ? "updated" : "published"));
          if (!dialog.photo) setPage(1);
          close();
          reload();
        }} />
      )}
      {dialog?.action === "delete" && (
        <DeletePhoto photo={dialog.photo} onClose={close} onDeleted={() => {
          toast.success(t("deleted"));
          close();
          reload();
        }} />
      )}
    </div>
  );
}
