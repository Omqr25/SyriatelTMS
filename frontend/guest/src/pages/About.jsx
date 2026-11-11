import { ArrowRight, ArrowLeft, Award, BriefcaseBusiness, Lightbulb, Target, UsersRound, Rocket } from 'lucide-react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import styles from './About.module.css';

export default function About(){const {t,i18n}=useTranslation();const Arrow=i18n.dir()==='rtl'?ArrowLeft:ArrowRight;return <>
<section className={styles.cover}><div className={styles.coverOverlay}/><div className={styles.coverInner}><span>{t('about.eyebrow')}</span><h1>{t('about.title')}</h1></div></section>
<section className={styles.about}><div className={styles.intro}><div><span className={styles.kicker}>SYRIATEL</span><h2>{t('about.introTitle')}</h2></div><div><p>{t('about.p1')}</p><p>{t('about.p2')}</p></div></div>
<div className={styles.infoGrid}><article><div className={styles.infoIcon}><Target/></div><h3>{t('about.vision')}</h3><p>{t('about.visionText')}</p></article><article><div className={styles.infoIcon}><Rocket/></div><h3>{t('about.mission')}</h3><p>{t('about.missionText')}</p></article></div>
<div className={styles.why}><div className={styles.whyHead}><span className={styles.kicker}>TALENT DEVELOPMENT</span><h2>{t('about.whyTitle')}</h2></div><div className={styles.whyCards}><Feature icon={BriefcaseBusiness} text={t('benefits.real.title')} /><Feature icon={UsersRound} text={t('benefits.mentor.title')} /><Feature icon={Lightbulb} text={t('benefits.skills.title')} /><Feature icon={Award} text={t('benefits.career.title')} /></div></div>
<div className={styles.cta}><div><h2>{t('about.cta')}</h2><p>{t('hero.description')}</p></div><Link to="/apply">{t('nav.apply')} <Arrow size={18}/></Link></div>
</section></>}
function Feature({icon:Icon,text}){return <div className={styles.feature}><Icon/><span>{text}</span></div>}
