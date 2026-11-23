import { useEffect, useRef, useState } from "react";
import { Expand, Images, X } from "lucide-react";
import { useTranslation } from "react-i18next";
import styles from "./Gallery.module.css";

const apiBase = (import.meta.env.VITE_API_URL || "").replace(/\/+$/, "");

function imageUrl(photo) {
  return apiBase + "/gallery/" + photo.id + "/image?v=" + encodeURIComponent(photo.image_version);
}

function Lightbox({ photo, onClose }) {
  const { t } = useTranslation();
  const dialog = useRef(null);

  useEffect(() => {
    const element = dialog.current;
    if (!element.open) element.showModal();
  }, []);

  return (
    <dialog ref={dialog} className={styles.lightbox} aria-labelledby="gallery-photo-title" onClose={onClose}>
      <div className={styles.lightboxHeader}>
        <h2 id="gallery-photo-title" dir="auto">{photo.title}</h2>
        <button type="button" onClick={() => dialog.current.close()} aria-label={t("gallery.close")}>
          <X size={22} aria-hidden="true" />
        </button>
      </div>
      <img className={styles.fullImage} src={imageUrl(photo)} alt={photo.title} />
      {photo.description && <p dir="auto">{photo.description}</p>}
    </dialog>
  );
}

export default function Gallery() {
  const { t } = useTranslation();
  const [photos, setPhotos] = useState([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [retry, setRetry] = useState(0);
  const [loading, setLoading] = useState(true);
  const [failed, setFailed] = useState(false);
  const [selected, setSelected] = useState(null);

  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    setFailed(false);
    fetch(apiBase + "/gallery?page=" + page, {
      signal: controller.signal,
      headers: { Accept: "application/json" },
    })
      .then(response => {
        if (!response.ok) throw new Error("Gallery request failed");
        return response.json();
      })
      .then(result => {
        if (controller.signal.aborted) return;
        setLastPage(result.last_page);
        setPhotos(previous => page === 1
          ? result.data
          : [...new Map([...previous, ...result.data].map(photo => [photo.id, photo])).values()]);
      })
      .catch(() => { if (!controller.signal.aborted) setFailed(true); })
      .finally(() => { if (!controller.signal.aborted) setLoading(false); });
    return () => controller.abort();
  }, [page, retry]);

  return (
    <section className={styles.page} aria-labelledby="gallery-heading">
      <header className={styles.heading}>
        <div className={styles.headingIcon}><Images size={28} aria-hidden="true" /></div>
        <h1 id="gallery-heading">{t("gallery.title")}</h1>
        <p>{t("gallery.intro")}</p>
      </header>
      {photos.length > 0 && (
        <ul className={styles.grid}>
          {photos.map(photo => (
            <li key={photo.id} className={styles.card}>
              <button className={styles.photoButton} type="button" onClick={() => setSelected(photo)}
                aria-label={t("gallery.view", { title: photo.title })}>
                <img src={imageUrl(photo)} loading="lazy" decoding="async" alt={photo.title} />
                <span className={styles.expand}><Expand size={17} aria-hidden="true" /></span>
              </button>
              <div className={styles.caption}>
                <h2 dir="auto">{photo.title}</h2>
                {photo.description && <p dir="auto">{photo.description}</p>}
              </div>
            </li>
          ))}
        </ul>
      )}
      {failed ? (
        <div className={styles.message} role="alert">
          <p>{t("gallery.failed")}</p>
          <button className={styles.secondary} onClick={() => setRetry(value => value + 1)}>{t("gallery.retry")}</button>
        </div>
      ) : loading ? (
        <p className={styles.message} role="status">{t("gallery.loading")}</p>
      ) : photos.length === 0 ? (
        <div className={styles.empty}>
          <Images size={30} aria-hidden="true" />
          <h2>{t("gallery.empty")}</h2>
          <p>{t("gallery.emptyHint")}</p>
        </div>
      ) : page < lastPage && (
        <div className={styles.message}>
          <button className={styles.secondary} onClick={() => { setLoading(true); setPage(value => value + 1); }}>{t("gallery.more")}</button>
        </div>
      )}
      {selected && <Lightbox photo={selected} onClose={() => setSelected(null)} />}
    </section>
  );
}
