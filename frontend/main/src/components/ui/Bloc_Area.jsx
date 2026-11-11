import styles from "./Bloc_Area.module.css"
export default function BlocArea({title,children,background="var(--surface)",justifyContent="Center"}){
    return(
    <>
        <div className={styles['bloc-area']}>
            <div className={styles['bloc-children']} style={{justifyContent:justifyContent, background:background} }>
                {children}
            </div> 
            <div className={styles['badge']}>
                {title}
            </div>
        </div>
    </>)
}