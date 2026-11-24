import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
export function connect({key,token,linkId,onEvent,onSubscribed,onState,onError}){
    window.Pusher=Pusher;
    const echo=new Echo({broadcaster:'reverb',key,wsHost:location.hostname,
        wsPort:Number(location.port||80),wssPort:Number(location.port||443),forceTLS:location.protocol==='https:',
        enabledTransports:['ws','wss'],disableStats:true,
        authorizer:channel=>({authorize:(socketId,callback)=>{
            fetch('/api/lab6/broadcasting/auth',{method:'POST',cache:'no-store',
                headers:{'Content-Type':'application/json',Accept:'application/json',Authorization:'Bearer '+token},
                body:JSON.stringify({socket_id:socketId,channel_name:channel.name})})
            .then(async response=>{if(!response.ok)throw new Error('غير مسموح بالاشتراك في قناة اللينك.');return response.json();})
            .then(data=>callback(null,data)).catch(error=>{callback(error,null);onError(error);});
        }})
    });
    const connection=echo.connector.pusher.connection;
    connection.bind('state_change',state=>onState(state.current));
    echo.private('links.'+linkId).listen('.LinkCountUpdated',onEvent).subscribed(onSubscribed).error(onError);
    return {disconnect(){echo.disconnect();}};
}
