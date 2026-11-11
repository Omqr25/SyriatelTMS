
import styles from "./Text_Field.module.css"
export default function TextField({lable,value}){
    return (<>
        <div className={styles['bloc-area']}>
            <p className={styles['lable']}>{lable}</p>
            <p className={styles['value']}>{value}</p>
        </div>
    </>)
}